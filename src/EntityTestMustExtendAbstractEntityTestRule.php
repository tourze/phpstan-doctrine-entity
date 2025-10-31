<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Tourze\PHPUnitBase\TestCaseHelper;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\PHPUnitDoctrineEntity\EntityChecker;

/**
 * 检查Entity的测试用例必须直接继承 AbstractEntityTestCase
 *
 * @implements Rule<InClassNode>
 */
readonly class EntityTestMustExtendAbstractEntityTestRule implements Rule
{
    public function __construct(private ReflectionProvider $reflectionProvider)
    {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        // 1. 检查是否是测试类
        if (!TestCaseHelper::isTestClass($classReflection->getName())) {
            return [];
        }

        $originalNode = $node->getOriginalNode();
        if (!$originalNode instanceof Class_) {
            return [];
        }

        // 2. 获取CoversClass注解
        $coversClass = TestCaseHelper::extractCoverClass($classReflection->getNativeReflection());
        if (null === $coversClass) {
            return [];
        }

        if (!$this->reflectionProvider->hasClass($coversClass)) {
            return [];
        }
        $coveredClassReflection = $this->reflectionProvider->getClass($coversClass);

        // 3. 检查被覆盖的类是否是Entity
        if (!EntityChecker::isEntityClass($coveredClassReflection->getNativeReflection())) {
            return [];
        }

        // 4. 检查测试类是否直接继承 AbstractEntityTest
        if (!$this->reflectionProvider->hasClass(AbstractEntityTestCase::class)) {
            return [];
        }
        $abstractTestReflection = $this->reflectionProvider->getClass(AbstractEntityTestCase::class);
        if (!$classReflection->isSubclassOfClass($abstractTestReflection)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    '测试类 %s 测试的是Entity类 %s，但没有继承 %s。',
                    $classReflection->getName(),
                    $coversClass,
                    AbstractEntityTestCase::class
                ))
                    ->identifier('entityTest.mustExtendAbstractEntityTest')
                    ->tip('Entity的测试必须继承 ' . AbstractEntityTestCase::class . ' 以使用预设的测试环境和辅助方法。')
                    ->build(),
            ];
        }

        return [];
    }
}
