<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitBase\TestCaseHelper;
use Tourze\PHPUnitDoctrineEntity\EntityChecker;

/**
 * 检查 Doctrine Entity 的构造函数不允许有参数
 * Entity 应该通过 getter/setter 来管理数据，而非构造函数注入
 *
 * @implements Rule<InClassNode>
 */
readonly class EntityParameterlessConstructorRule implements Rule
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

        if ($this->shouldSkipTestClass($classReflection)) {
            return [];
        }

        $originalNode = $node->getOriginalNode();
        if (!($originalNode instanceof Class_ || $originalNode instanceof Enum_)) {
            return [];
        }

        if (!EntityChecker::isEntityClass($classReflection->getNativeReflection())) {
            return [];
        }

        return $this->checkConstructor($classReflection, $node);
    }

    private function shouldSkipTestClass(ClassReflection $classReflection): bool
    {
        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return false;
        }
        $file = (string) $classReflection->getFileName();
        $inFixtures = str_contains($file, '/tests/Fixtures/') || str_contains($file, '/tests/fixtures/');

        return !$inFixtures;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function checkConstructor(ClassReflection $classReflection, InClassNode $node): array
    {
        $errors = [];
        $native = $classReflection->getNativeReflection();
        if ($native->hasMethod('__construct')) {
            $ctor = $native->getConstructor();
            if (null !== $ctor && $ctor->getNumberOfParameters() > 0) {
                $line = $ctor->getStartLine();
                if ($line <= 0) {
                    $line = $node->getOriginalNode()->getStartLine();
                }
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Entity %s 的构造函数不允许有参数。请移除构造函数参数，使用 setter 方法来设置实体数据。',
                    $classReflection->getName()
                ))
                    ->line($line)
                    ->identifier('doctrine.entity.ctor.noParams')
                    ->build()
                ;
            }
        }

        return $errors;
    }
}
