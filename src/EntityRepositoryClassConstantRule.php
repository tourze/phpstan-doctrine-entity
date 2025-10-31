<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitDoctrineEntity\EntityChecker;

/**
 * 检查 Entity 类在指定 repositoryClass 时必须使用 ClassName::class 语法而不是字符串
 *
 * @implements Rule<InClassNode>
 */
readonly class EntityRepositoryClassConstantRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();

        // 检查是否是实体类
        if (!EntityChecker::isEntityClass($classReflection->getNativeReflection())) {
            return [];
        }

        return $this->collectErrorsFromAttributes($node->getOriginalNode(), $classReflection);
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function collectErrorsFromAttributes(ClassLike $classNode, ClassReflection $classReflection): array
    {
        $errors = [];
        foreach ($classNode->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (!$this->isEntityAttribute($attr)) {
                    continue;
                }
                $error = $this->checkEntityAttribute($attr, $classReflection);
                if (null !== $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    private function isEntityAttribute(Attribute $attr): bool
    {
        $name = $attr->name->toString();

        return 'Doctrine\ORM\Mapping\Entity' === $name
            || 'ORM\Entity' === $name
            || 'Entity' === $name;
    }

    private function checkEntityAttribute(Attribute $attr, ClassReflection $classReflection): ?IdentifierRuleError
    {
        foreach ($attr->args as $arg) {
            if (!$arg instanceof Arg || null === $arg->name || 'repositoryClass' !== $arg->name->name) {
                continue;
            }

            // 字符串不允许
            if ($arg->value instanceof String_) {
                return RuleErrorBuilder::message(sprintf(
                    '实体类 %s 在指定 repositoryClass 时应该使用 ClassName::class 语法而不是字符串 "%s"。',
                    $classReflection->getName(),
                    $arg->value->value
                ))
                    ->line($arg->value->getStartLine())
                    ->identifier('doctrine.entity.repository.classConst')
                    ->build()
                ;
            }

            // 必须是 ClassConstFetch
            if (!($arg->value instanceof ClassConstFetch)) {
                return RuleErrorBuilder::message(sprintf(
                    '实体类 %s 必须在 repositoryClass 参数中使用 ClassName::class 语法。',
                    $classReflection->getName()
                ))
                    ->line($arg->value->getStartLine())
                    ->identifier('doctrine.entity.repository.classConst')
                    ->build()
                ;
            }

            // 并且常量名必须是 ::class
            $constName = $arg->value->name;
            if (!($constName instanceof Node\Identifier) || 'class' !== $constName->name) {
                return RuleErrorBuilder::message(sprintf(
                    '实体类 %s 必须在 repositoryClass 参数中使用 ::class 常量。',
                    $classReflection->getName()
                ))
                    ->line($arg->value->getStartLine())
                    ->identifier('doctrine.entity.repository.classConst')
                    ->build()
                ;
            }
        }

        return null;
    }
}
