<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
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
 * 检查实体的 ORM\Column 是否在 options 参数中设置了字段注释
 *
 * @implements Rule<InClassNode>
 */
readonly class EntityColumnCommentRule implements Rule
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

        // 跳过测试目录
        $fileName = $classReflection->getFileName();
        if (null !== $fileName && str_contains($fileName, '/tests')) {
            return [];
        }

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
        $optionsArg = $this->findNamedArg($attr, 'options');
        $hasOptions = null !== $optionsArg;
        $hasComment = $hasOptions && $optionsArg->value instanceof Array_ ? $this->hasCommentInOptions($optionsArg->value) : false;

        $propertyName = $property->props[0]->name->name ?? 'unknown';

        // 如果没有 options 参数，建议添加
        if (!$hasOptions) {
            return RuleErrorBuilder::message(sprintf(
                '实体 %s 中的属性 $%s 应该在 #[ORM\Column] 注解中添加 options 参数并包含 comment (例如：options: [\'comment\' => \'字段说明\'])。',
                $classReflection->getName(),
                $propertyName
            ))
                ->line($attr->getStartLine())
                ->identifier('doctrine.column.comment.missingOptions')
                ->build()
            ;
        }

        // 如果有 options 但没有 comment，建议添加
        if (!$hasComment) {
            return RuleErrorBuilder::message(sprintf(
                '实体 %s 中的属性 $%s 有 options 参数但缺少 \'comment\' 键。请添加注释来描述字段 (例如：options: [\'comment\' => \'字段说明\'])。',
                $classReflection->getName(),
                $propertyName
            ))
                ->line(null !== $optionsArg ? $optionsArg->getStartLine() : $attr->getStartLine())
                ->identifier('doctrine.column.comment.missingKey')
                ->build()
            ;
        }

        return null;
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

    private function hasCommentInOptions(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem) {
                continue;
            }

            // 检查键是否是 'comment'
            if ($item->key instanceof String_ && 'comment' === $item->key->value) {
                // 还可以检查值是否为空
                if ($item->value instanceof String_ && '' === trim($item->value->value)) {
                    return false; // 注释为空也视为没有注释
                }

                return true;
            }
        }

        return false;
    }
}
