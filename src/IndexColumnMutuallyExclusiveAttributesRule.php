<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity;

use Doctrine\ORM\Mapping as ORM;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\PHPUnitDoctrineEntity\EntityChecker;

/**
 * 检查 IndexColumn 与关联字段的互斥规则
 * 确保 IndexColumn 不能用于关联字段（因为关联字段通常不需要独立索引）
 *
 * @implements Rule<InClassNode>
 */
readonly class IndexColumnMutuallyExclusiveAttributesRule implements Rule
{
    /**
     * IndexColumn 不能与以下 ORM 关联属性同时使用
     */
    private const MUTUALLY_EXCLUSIVE_WITH_INDEX_COLUMN = [
        ORM\ManyToOne::class,
        ORM\OneToOne::class,
        ORM\ManyToMany::class,
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!class_exists(IndexColumn::class)) {
            return [];
        }

        $classReflection = $node->getClassReflection();

        // 只检查实体类
        if (!EntityChecker::isEntityClass($classReflection->getNativeReflection())) {
            return [];
        }

        $errors = [];
        $classNode = $node->getOriginalNode();

        // 遍历类的所有属性
        foreach ($classNode->stmts as $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }

            $propertyErrors = $this->checkProperty($stmt, $classReflection);
            $errors = array_merge($errors, $propertyErrors);
        }

        return $errors;
    }

    /**
     * 检查单个属性的互斥规则
     *
     * @return list<IdentifierRuleError>
     */
    private function checkProperty(Property $property, ClassReflection $classReflection): array
    {
        $propertyName = $property->props[0]->name->toString();
        $attributeInfo = $this->collectPropertyAttributes($property);

        return $this->validateAttributeCombination($attributeInfo, $property, $classReflection, $propertyName);
    }

    /**
     * 收集属性上的所有相关注释
     *
     * @return array{hasIndexColumn: bool, associationAttributes: array<string>}
     */
    private function collectPropertyAttributes(Property $property): array
    {
        $hasIndexColumn = false;
        $associationAttributes = [];

        foreach ($property->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attributeName = $this->getAttributeName($attr);

                if ($this->isIndexColumnAttribute($attributeName)) {
                    $hasIndexColumn = true;
                }

                $associationAttribute = $this->findMatchingAssociation($attributeName);
                if (null !== $associationAttribute) {
                    $associationAttributes[] = $associationAttribute;
                }
            }
        }

        return [
            'hasIndexColumn' => $hasIndexColumn,
            'associationAttributes' => $associationAttributes,
        ];
    }

    /**
     * 查找匹配的关联属性
     */
    private function findMatchingAssociation(string $attributeName): ?string
    {
        foreach (self::MUTUALLY_EXCLUSIVE_WITH_INDEX_COLUMN as $associationType) {
            if ($this->isAttributeMatch($attributeName, $associationType)) {
                return $attributeName;
            }
        }

        return null;
    }

    /**
     * 验证属性组合是否有效
     *
     * @param array{hasIndexColumn: bool, associationAttributes: array<string>} $attributeInfo
     * @return list<IdentifierRuleError>
     */
    private function validateAttributeCombination(array $attributeInfo, Property $property, ClassReflection $classReflection, string $propertyName): array
    {
        if (!$attributeInfo['hasIndexColumn'] || [] === $attributeInfo['associationAttributes']) {
            return [];
        }

        $errors = [];
        foreach ($attributeInfo['associationAttributes'] as $associationAttribute) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                '实体类 "%s" 的属性 "%s" 同时使用了 #[IndexColumn] 和 #[%s] 属性，这些属性是互斥的，不能同时使用。',
                $classReflection->getName(),
                $propertyName,
                $this->getShortAttributeName($associationAttribute)
            ))
                ->line($property->getStartLine())
                ->tip(sprintf(
                    '关联字段通常会自动创建外键索引，不需要额外的索引。请从属性 "%s" 中移除 #[IndexColumn] 属性。',
                    $propertyName
                ))
                ->identifier('doctrine.indexColumn.mutuallyExclusive')
                ->build()
            ;
        }

        return $errors;
    }

    /**
     * 获取注释名称
     */
    private function getAttributeName(Attribute $attribute): string
    {
        return $attribute->name->toString();
    }

    /**
     * 检查是否是 IndexColumn 属性
     */
    private function isIndexColumnAttribute(string $attributeName): bool
    {
        return $this->isAttributeMatch($attributeName, IndexColumn::class);
    }

    /**
     * 检查属性名是否匹配（支持完整类名和短类名匹配）
     */
    private function isAttributeMatch(string $attributeName, string $targetAttribute): bool
    {
        // 完整匹配
        if ($attributeName === $targetAttribute) {
            return true;
        }

        // 获取短类名
        $shortAttributeName = $this->getShortAttributeName($attributeName);
        $shortTargetAttribute = $this->getShortAttributeName($targetAttribute);

        // 短类名匹配
        if ($shortAttributeName === $shortTargetAttribute) {
            return true;
        }

        // 特殊处理：ORM\ManyToOne 应该匹配 Doctrine\ORM\Mapping\ManyToOne
        $normalizedAttributeName = $this->normalizeAttributeName($attributeName);
        $normalizedTargetAttribute = $this->normalizeAttributeName($targetAttribute);

        return $normalizedAttributeName === $normalizedTargetAttribute;
    }

    /**
     * 获取短注释名称（用于显示）
     */
    private function getShortAttributeName(string $attributeName): string
    {
        $lastBackslash = strrpos($attributeName, '\\');
        if (false !== $lastBackslash) {
            return substr($attributeName, $lastBackslash + 1);
        }

        return $attributeName;
    }

    /**
     * 规范化属性名称，将常见别名转换为完整类名
     */
    private function normalizeAttributeName(string $attributeName): string
    {
        // 常见的 Doctrine ORM 别名映射
        $aliasMap = [
            'ORM\Column' => 'Doctrine\ORM\Mapping\Column',
            'ORM\Entity' => 'Doctrine\ORM\Mapping\Entity',
            'ORM\Table' => 'Doctrine\ORM\Mapping\Table',
            'ORM\Id' => 'Doctrine\ORM\Mapping\Id',
            'ORM\ManyToOne' => 'Doctrine\ORM\Mapping\ManyToOne',
            'ORM\OneToOne' => 'Doctrine\ORM\Mapping\OneToOne',
            'ORM\OneToMany' => 'Doctrine\ORM\Mapping\OneToMany',
            'ORM\ManyToMany' => 'Doctrine\ORM\Mapping\ManyToMany',
            'ORM\JoinColumn' => 'Doctrine\ORM\Mapping\JoinColumn',
            'ORM\JoinTable' => 'Doctrine\ORM\Mapping\JoinTable',
        ];

        return $aliasMap[$attributeName] ?? $attributeName;
    }
}
