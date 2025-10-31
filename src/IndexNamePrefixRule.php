<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity;

use Doctrine\ORM\Mapping as ORM;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitDoctrineEntity\EntityChecker;

/**
 * @implements Rule<InClassNode>
 */
class IndexNamePrefixRule implements Rule
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
        $classReflection = $scope->getClassReflection();

        if (null === $classReflection || !EntityChecker::isEntityClass($classReflection->getNativeReflection())) {
            return [];
        }

        $classNode = $node->getOriginalNode();
        if (!$classNode instanceof Class_) {
            return [];
        }

        $tableName = $this->getTableName($classNode);
        if (null === $tableName) {
            // If no table name is defined, we cannot check the index prefix.
            // Another rule should enforce that entities have a table name.
            return [];
        }

        $errors = [];

        foreach ($classNode->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if (!$this->isOrmIndexAttribute($attribute)) {
                    continue;
                }
                $indexName = $this->getAttributeNamedArgument($attribute, 'name');
                if (null === $indexName || str_starts_with($indexName, $tableName . '_')) {
                    continue;
                }
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'Index "%s" in entity "%s" must be prefixed with the table name "%s_".',
                        $indexName,
                        $classReflection->getName(),
                        $tableName
                    )
                )
                    ->line($attribute->getStartLine())
                    ->tip(sprintf('Rename the index to something like "%s_%s".', $tableName, $indexName))
                    ->identifier('doctrine.indexName.prefix')
                    ->build()
                ;
            }
        }

        return $errors;
    }

    private function getTableName(Class_ $classNode): ?string
    {
        foreach ($classNode->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attribute) {
                if ($this->isOrmTableAttribute($attribute)) {
                    return $this->getAttributeNamedArgument($attribute, 'name');
                }
            }
        }

        return null;
    }

    private function isOrmTableAttribute(Attribute $attribute): bool
    {
        $attributeName = $attribute->name->toString();

        return ORM\Table::class === $attributeName || 'Table' === $attributeName;
    }

    private function getAttributeNamedArgument(Attribute $attribute, string $argName): ?string
    {
        foreach ($attribute->args as $arg) {
            if (null !== $arg->name && $arg->name->toString() === $argName) {
                if ($arg->value instanceof Node\Scalar\String_) {
                    return $arg->value->value;
                }
            }
        }

        return null;
    }

    private function isOrmIndexAttribute(Attribute $attribute): bool
    {
        $attributeName = $attribute->name->toString();

        return ORM\Index::class === $attributeName || 'Index' === $attributeName;
    }
}
