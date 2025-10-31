<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity;

use Doctrine\DBAL\Types\Types;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitDoctrineEntity\EntityChecker;

/**
 * 检查时间相关字段应使用 Types::DATETIME_IMMUTABLE 以减少时间相关错误
 *
 * @implements Rule<InClassNode>
 */
readonly class EntityDateTimeImmutableRule implements Rule
{
    private const MUTABLE_TO_IMMUTABLE_MAPPING = [
        'datetime' => 'Types::DATETIME_IMMUTABLE',
        'DATETIME_MUTABLE' => 'Types::DATETIME_IMMUTABLE',
        'date' => 'Types::DATE_IMMUTABLE',
        'DATE_MUTABLE' => 'Types::DATE_IMMUTABLE',
        'time' => 'Types::TIME_IMMUTABLE',
        'TIME_MUTABLE' => 'Types::TIME_IMMUTABLE',
        'datetimetz' => 'Types::DATETIMETZ_IMMUTABLE',
        'DATETIMETZ_MUTABLE' => 'Types::DATETIMETZ_IMMUTABLE',
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        // 检查是否是实体类
        if (!EntityChecker::isEntityClass($classReflection->getNativeReflection())) {
            return [];
        }

        $errors = [];
        $classNode = $node->getOriginalNode();

        // 遍历类的所有属性
        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof Property) {
                $propertyErrors = $this->checkProperty($stmt, $classReflection);
                $errors = array_merge($errors, $propertyErrors);
            }
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkProperty(Property $property, ClassReflection $classReflection): array
    {
        $errors = [];

        // 检查属性的属性（PHP attributes）
        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isColumnAttribute($attr)) {
                    $error = $this->checkColumnAttribute($attr, $property, $classReflection);
                    if (null !== $error) {
                        $errors[] = $error;
                    }
                }
            }
        }

        return $errors;
    }

    private function isColumnAttribute(Attribute $attr): bool
    {
        $name = $attr->name->toString();

        return 'Doctrine\ORM\Mapping\Column' === $name
            || 'ORM\Column' === $name
            || 'Column' === $name;
    }

    private function checkColumnAttribute(Attribute $attr, Property $property, ClassReflection $classReflection): ?IdentifierRuleError
    {
        $typeArg = $this->findNamedArg($attr, 'type');
        if (null === $typeArg) {
            return null;
        }

        $propertyName = $property->props[0]->name->name ?? 'unknown';

        if ($typeArg->value instanceof String_) {
            return $this->checkStringType($typeArg, $classReflection, $propertyName);
        }

        if ($typeArg->value instanceof ClassConstFetch) {
            return $this->checkConstType($typeArg, $classReflection, $propertyName);
        }

        return null;
    }

    private function checkStringType(Arg $arg, ClassReflection $classReflection, string $propertyName): ?IdentifierRuleError
    {
        $typeString = $arg->value instanceof String_ ? $arg->value->value : null;
        if (null === $typeString) {
            return null;
        }
        if (!isset(self::MUTABLE_TO_IMMUTABLE_MAPPING[$typeString])) {
            return null;
        }
        $suggestedType = self::MUTABLE_TO_IMMUTABLE_MAPPING[$typeString];

        return RuleErrorBuilder::message(sprintf(
            '实体 %s 中的属性 $%s 应该使用 %s 而不是可变类型 "%s" 以防止时间相关错误。',
            $classReflection->getName(),
            $propertyName,
            $suggestedType,
            $typeString
        ))
            ->line($arg->value->getStartLine())
            ->identifier('doctrine.datetime.immutable.string')
            ->build()
        ;
    }

    private function checkConstType(Arg $arg, ClassReflection $classReflection, string $propertyName): ?IdentifierRuleError
    {
        $className = $arg->value instanceof ClassConstFetch ? $arg->value->class : null;
        $constantName = $arg->value instanceof ClassConstFetch ? $arg->value->name : null;
        if (!($className instanceof Name) || !($constantName instanceof Identifier)) {
            return null;
        }
        $classNameStr = $className->toString();
        if (!(str_ends_with($classNameStr, 'Types') || Types::class === $classNameStr)) {
            return null;
        }
        $constantNameStr = $constantName->name;
        if (!isset(self::MUTABLE_TO_IMMUTABLE_MAPPING[$constantNameStr])) {
            return null;
        }
        $suggestedType = self::MUTABLE_TO_IMMUTABLE_MAPPING[$constantNameStr];

        return RuleErrorBuilder::message(sprintf(
            '实体 %s 中的属性 $%s 应该使用 %s 而不是可变类型 Types::%s 以防止时间相关错误。',
            $classReflection->getName(),
            $propertyName,
            $suggestedType,
            $constantNameStr
        ))
            ->line($arg->value->getStartLine())
            ->identifier('doctrine.datetime.immutable.const')
            ->build()
        ;
    }

    private function findNamedArg(Attribute $attr, string $name): ?Arg
    {
        foreach ($attr->args as $arg) {
            if ($arg instanceof Arg && null !== $arg->name && $arg->name->name === $name) {
                return $arg;
            }
        }

        return null;
    }
}
