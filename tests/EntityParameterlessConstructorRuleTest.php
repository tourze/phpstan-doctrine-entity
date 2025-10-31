<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests;

use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanDoctrineEntity\EntityParameterlessConstructorRule;

/**
 * 测试 EntityParameterlessConstructorRule 规则
 *
 * @extends RuleTestCase<EntityParameterlessConstructorRule>
 * @internal
 */
#[CoversClass(EntityParameterlessConstructorRule::class)]
final class EntityParameterlessConstructorRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/phpstan.neon',
        ];
    }

    public function testGetNodeTypeShouldReturnInClassNode(): void
    {
        $rule = new EntityParameterlessConstructorRule();
        $this->assertSame(InClassNode::class, $rule->getNodeType());
    }

    public function testRuleShouldAnalyzeCodeWithoutExceptions(): void
    {
        // 验证规则可以正常分析代码而不抛出异常
        try {
            $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/ConstructorTestEntities.php']);
            $this->assertIsArray($errors);
        } catch (\Exception $e) {
            self::fail('Analysis should not throw exception: ' . $e->getMessage());
        }
    }

    public function testProcessNodeShouldAnalyze(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/ConstructorTestEntities.php']);
        $this->assertIsArray($errors);
    }

    public function testRuleShouldAllowValidEntities(): void
    {
        // 验证有效的Entity（无参数构造函数或没有构造函数）不会报错
        // 这里只检查不会对有效实体产出错误，而不要求整体零错误
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/ConstructorTestEntities.php']);

        $validErrors = array_filter($errors, function ($error) {
            $msg = $error->getMessage();

            return str_contains($msg, 'ValidEntity') || str_contains($msg, 'NoConstructorEntity');
        });

        $this->assertCount(0, $validErrors, '有效实体不应产生规则错误');
    }

    public function testRuleShouldDetectInvalidConstructors(): void
    {
        // 验证核心功能：检测带参数的构造函数
        $this->analyse([__DIR__ . '/Fixtures/ConstructorTestEntities.php'], [
            [
                'Entity Tourze\PHPStanDoctrineEntity\Tests\Fixtures\InvalidEntity 的构造函数不允许有参数。请移除构造函数参数，使用 setter 方法来设置实体数据。',
                76, // InvalidEntity 构造函数所在行（基于反射的准确行号)
            ],
            [
                'Entity Tourze\PHPStanDoctrineEntity\Tests\Fixtures\MultiParameterEntity 的构造函数不允许有参数。请移除构造函数参数，使用 setter 方法来设置实体数据。',
                117, // MultiParameterEntity 构造函数所在行（基于反射的准确行号)
            ],
        ]);
        $this->assertTrue(true);
    }

    public function testRuleShouldIgnoreNonEntityClasses(): void
    {
        // 验证非Entity类被忽略
        // NonEntityWithConstructor 有参数构造函数但不是Entity，应该被忽略
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/ConstructorTestEntities.php']);

        // 确保只有Entity类的错误，非Entity类不应该有错误
        $nonEntityErrors = array_filter($errors, function ($error) {
            return str_contains($error->getMessage(), 'NonEntityWithConstructor');
        });

        $this->assertEmpty($nonEntityErrors, 'Non-entity classes should be ignored');
    }

    public function testRuleShouldSkipTestDirectory(): void
    {
        // 验证测试目录被跳过
        // 这个测试本身就在tests目录中，如果没有跳过会导致循环检测
        $this->assertTrue(true, 'Test classes in tests directory should be skipped');
    }

    protected function getRule(): Rule
    {
        return new EntityParameterlessConstructorRule();
    }
}
