<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests;

use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanDoctrineEntity\EntityTestMustExtendAbstractEntityTestRule;

/**
 * 测试 EntityTestMustExtendAbstractEntityTestRule 规则
 *
 * @extends RuleTestCase<EntityTestMustExtendAbstractEntityTestRule>
 * @internal
 */
#[CoversClass(EntityTestMustExtendAbstractEntityTestRule::class)]
final class EntityTestMustExtendAbstractEntityTestRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/phpstan.neon',
        ];
    }

    public function testGetNodeTypeShouldReturnInClassNode(): void
    {
        $rule = new EntityTestMustExtendAbstractEntityTestRule($this->createReflectionProvider());

        $this->assertSame(InClassNode::class, $rule->getNodeType());
    }

    public function testRuleShouldAnalyzeCodeWithoutExceptions(): void
    {
        // This test verifies that our rule can analyze code without crashing
        try {
            $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
            $this->assertIsArray($errors);
        } catch (\Exception $e) {
            self::fail('Analysis should not throw exception: ' . $e->getMessage());
        }
    }

    public function testProcessNodeShouldAnalyze(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $this->assertIsArray($errors);
    }

    public function testRuleShouldDetectInvalidEntityTests(): void
    {
        // 验证核心功能：检测未继承 AbstractEntityTestCase 的实体测试
        $this->analyse([__DIR__ . '/Fixtures/EntityTestFiles.php'], [
            [
                "测试类 Tourze\\PHPStanDoctrineEntity\\Tests\\Data\\InvalidEntityTest 测试的是Entity类 Tourze\\PHPStanDoctrineEntity\\Tests\\Data\\SampleEntity，但没有继承 Tourze\\PHPUnitDoctrineEntity\\AbstractEntityTestCase。\n    💡 Entity的测试必须继承 Tourze\\PHPUnitDoctrineEntity\\AbstractEntityTestCase 以使用预设的测试环境和辅助方法。",
                77,
            ],
        ]);

        // 附加的有效性断言：规则实例类型校验
        $this->assertInstanceOf(Rule::class, $this->getRule());
    }

    // TODO: Re-enable when PHPStan autoloading issues are resolved
    // public function testRuleShouldDetectInvalidEntityTests(): void
    // {
    //     // This comprehensive test validates the core functionality
    //     // It should detect when entity test classes don't extend AbstractEntityTestCase

    //     $this->analyse([__DIR__ . '/data/EntityTestFiles.php'], [
    //         [
    //             '测试类 Tourze\PHPStanDoctrineEntity\Tests\Data\InvalidEntityTest 测试的是Entity类 Tourze\PHPStanDoctrineEntity\Tests\Data\SampleEntity，但没有继承 Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase。',
    //             72, // line number where InvalidEntityTest class is defined
    //         ],
    //     ]);
    // }

    public function testRuleShouldProvideHelpfulErrorMessages(): void
    {
        // This test verifies that error messages are helpful and informative
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '但没有继承'));
        foreach ($targetErrors as $error) {
            $message = $error->getMessage();
            // Error message should contain helpful information
            $this->assertStringContainsString('测试类', $message);
            $this->assertStringContainsString('Entity类', $message);
            $this->assertStringContainsString('AbstractEntityTestCase', $message);

            // Tip should be provided
            $tip = $error->getTip();
            $this->assertNotNull($tip);
            $this->assertStringContainsString('Entity的测试必须继承', $tip);
        }
    }

    public function testRuleShouldUseCorrectIdentifier(): void
    {
        // This test verifies that the rule uses the correct error identifier
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        $targetErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), '但没有继承'));
        foreach ($targetErrors as $error) {
            $this->assertEquals('entityTest.mustExtendAbstractEntityTest', $error->getIdentifier());
        }
    }

    public function testRuleShouldIgnoreNonTestClasses(): void
    {
        // Non-test classes should be ignored by the rule
        // This is verified by the fact that RegularClass in the data file
        // doesn't generate any errors despite not being a test class
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $regularClassErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), 'RegularClass'));
        $this->assertCount(0, $regularClassErrors, '普通类应被规则忽略，不应产出错误');
    }

    public function testRuleShouldIgnoreTestsWithoutCoversClass(): void
    {
        // Tests without CoversClass annotation should be ignored
        // This is verified by the fact that TestWithoutCoversClass in the data file
        // doesn't generate any errors despite not having a CoversClass annotation
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $withoutCoversErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), 'TestWithoutCoversClass'));
        $this->assertCount(0, $withoutCoversErrors, '缺少 CoversClass 的测试应被忽略');
    }

    public function testRuleShouldIgnoreTestsCoveringNonEntityClasses(): void
    {
        // Tests covering non-entity classes should be ignored
        // This is verified by the fact that RegularClassTest in the data file
        // doesn't generate any errors despite covering a non-entity class
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $nonEntityErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), 'RegularClassTest'));
        $this->assertCount(0, $nonEntityErrors, '覆盖非实体类的测试应被忽略');
    }

    public function testRuleShouldAllowValidEntityTests(): void
    {
        // Valid entity tests extending AbstractEntityTestCase should not generate errors
        // This is verified by the fact that ValidEntityTest in the data file
        // doesn't generate any errors
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $validErrors = array_filter($errors, static fn ($e) => str_contains($e->getMessage(), 'ValidEntityTest'));
        $this->assertCount(0, $validErrors, '合法的实体测试不应产出错误');
    }

    protected function getRule(): Rule
    {
        return new EntityTestMustExtendAbstractEntityTestRule($this->createReflectionProvider());
    }
}
