<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * 检查 Assert\Range 同时设置 min/max 与 minMessage/maxMessage 的非法组合
 *
 * @implements Rule<InClassNode>
 */
readonly class RangeConstraintMessageRule implements Rule
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
        $classNode = $node->getOriginalNode();
        if (!$classNode instanceof Class_) {
            return [];
        }

        $classReflection = $node->getClassReflection();
        $errors = [];

        foreach ($classNode->stmts as $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }

            $propertyName = $stmt->props[0]->name->name ?? 'property';
            foreach ($stmt->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if (!$this->isRangeAttribute($attr)) {
                        continue;
                    }

                    $rangeFlags = $this->extractRangeOptions($attr);
                    if ($this->hasInvalidMessages($rangeFlags)) {
                        $errors[] = $this->buildError($classReflection, $propertyName, $attr, $rangeFlags);
                    }
                }
            }
        }

        return $errors;
    }

    private function isRangeAttribute(Attribute $attr): bool
    {
        $name = ltrim($attr->name->toString(), '\\');

        return in_array($name, ['Symfony\Component\Validator\Constraints\Range', 'Assert\Range', 'Range'], true);
    }

    /**
     * @return array{hasMin: bool, hasMax: bool, hasMinMessage: bool, hasMaxMessage: bool, messageLine: int|null}
     */
    private function extractRangeOptions(Attribute $attr): array
    {
        $flags = [
            'hasMin' => false,
            'hasMax' => false,
            'hasMinMessage' => false,
            'hasMaxMessage' => false,
            'messageLine' => null,
        ];

        foreach ($attr->args as $arg) {
            $this->updateFlagsFromArg($arg, $flags);
        }

        return $flags;
    }

    /**
     * @param array{hasMin: bool, hasMax: bool, hasMinMessage: bool, hasMaxMessage: bool, messageLine: int|null} $flags
     */
    private function updateFlagsFromArg(Arg $arg, array &$flags): void
    {
        if (null !== $arg->name) {
            $name = $arg->name->name;
            if ('min' === $name) {
                $flags['hasMin'] = true;
            }
            if ('max' === $name) {
                $flags['hasMax'] = true;
            }
            if ('minMessage' === $name) {
                $flags['hasMinMessage'] = true;
                $flags['messageLine'] = $arg->getStartLine();
            }
            if ('maxMessage' === $name) {
                $flags['hasMaxMessage'] = true;
                $flags['messageLine'] = $arg->getStartLine();
            }

            return;
        }

        // 处理类似 #[Assert\Range(['min' => 1, 'max' => 10])] 的数组传参
        if ($arg->value instanceof Array_) {
            $this->updateFlagsFromArray($arg->value, $flags);
        }
    }

    /**
     * @param array{hasMin: bool, hasMax: bool, hasMinMessage: bool, hasMaxMessage: bool, messageLine: int|null} $flags
     */
    private function updateFlagsFromArray(Array_ $array, array &$flags): void
    {
        foreach ($array->items as $item) {
            if (!$item instanceof ArrayItem || !$item->key instanceof String_) {
                continue;
            }

            $key = $item->key->value;
            if ('min' === $key) {
                $flags['hasMin'] = true;
            }
            if ('max' === $key) {
                $flags['hasMax'] = true;
            }
            if ('minMessage' === $key) {
                $flags['hasMinMessage'] = true;
                $flags['messageLine'] = $item->getStartLine();
            }
            if ('maxMessage' === $key) {
                $flags['hasMaxMessage'] = true;
                $flags['messageLine'] = $item->getStartLine();
            }
        }
    }

    /**
     * @param array{hasMin: bool, hasMax: bool, hasMinMessage: bool, hasMaxMessage: bool, messageLine: int|null} $flags
     */
    private function hasInvalidMessages(array $flags): bool
    {
        return $flags['hasMin'] && $flags['hasMax'] && ($flags['hasMinMessage'] || $flags['hasMaxMessage']);
    }

    /**
     * @param array{hasMin: bool, hasMax: bool, hasMinMessage: bool, hasMaxMessage: bool, messageLine: int|null} $flags
     */
    private function buildError(ClassReflection $classReflection, string $propertyName, Attribute $attr, array $flags): IdentifierRuleError
    {
        $line = $flags['messageLine'] ?? $attr->getStartLine();

        return RuleErrorBuilder::message(sprintf(
            '类 %s 的属性 $%s 使用 #[Assert\Range] 同时设置了 min/max 和 minMessage/maxMessage，会触发 ConstraintDefinitionException；请改用 notInRangeMessage。',
            $classReflection->getName(),
            $propertyName
        ))
            ->line($line)
            ->identifier('validator.range.invalidMessages')
            ->tip('当同时设置 min 与 max 时只能使用 notInRangeMessage，自定义上下限提示请写在该参数。')
            ->build()
        ;
    }
}
