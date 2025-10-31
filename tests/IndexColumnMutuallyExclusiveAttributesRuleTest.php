<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests;

use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPStanDoctrineEntity\IndexColumnMutuallyExclusiveAttributesRule;

/**
 * 测试 IndexColumnMutuallyExclusiveAttributesRule 规则
 *
 * @extends RuleTestCase<IndexColumnMutuallyExclusiveAttributesRule>
 * @internal
 */
#[CoversClass(IndexColumnMutuallyExclusiveAttributesRule::class)]
final class IndexColumnMutuallyExclusiveAttributesRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/phpstan.neon',
        ];
    }

    public function testGetNodeTypeShouldReturnInClassNode(): void
    {
        $rule = new IndexColumnMutuallyExclusiveAttributesRule();

        $this->assertSame(InClassNode::class, $rule->getNodeType());
    }

    public function testRuleShouldAnalyzeCodeWithoutExceptions(): void
    {
        // This test verifies that our rule can analyze code without crashing
        try {
            $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
            // If we get here without exception, the analysis succeeded
            // We expect errors from our test data, which is normal
            $this->assertGreaterThan(0, count($errors));
        } catch (\Exception $e) {
            self::fail('Analysis should not throw exception: ' . $e->getMessage());
        }
    }

    public function testProcessNodeShouldAnalyze(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);
        $this->assertIsArray($errors);
    }

    // TODO: Re-enable when PHPStan autoloading issues are resolved
    // public function testRuleShouldDetectIndexColumnAndManyToOneConflicts(): void
    // {
    //     $this->analyse([__DIR__ . '/data/EntityTestFiles.php'], [
    //         [
    //             '实体类 "Tourze\PHPStanDoctrineEntity\Tests\Data\ConflictingManyToOneEntity" 的属性 "sampleEntity" 同时使用了 #[IndexColumn] 和 #[ManyToOne] 属性，这些属性是互斥的，不能同时使用。',
    //             155, // line number where the property is defined in ConflictingManyToOneEntity
    //         ],
    //         [
    //             '实体类 "Tourze\PHPStanDoctrineEntity\Tests\Data\ConflictingOneToOneEntity" 的属性 "sampleEntity" 同时使用了 #[IndexColumn] 和 #[OneToOne] 属性，这些属性是互斥的，不能同时使用。',
    //             172, // line number where the property is defined in ConflictingOneToOneEntity
    //         ],
    //         [
    //             '实体类 "Tourze\PHPStanDoctrineEntity\Tests\Data\ConflictingManyToManyEntity" 的属性 "sampleEntities" 同时使用了 #[IndexColumn] 和 #[ManyToMany] 属性，这些属性是互斥的，不能同时使用。',
    //             188, // line number where the property is defined in ConflictingManyToManyEntity
    //         ],
    //         [
    //             '实体类 "Tourze\PHPStanDoctrineEntity\Tests\Data\MultiPropertyEntity" 的属性 "conflictProperty" 同时使用了 #[IndexColumn] 和 #[ManyToOne] 属性，这些属性是互斥的，不能同时使用。',
    //             207, // line number where the property is defined in MultiPropertyEntity
    //         ],
    //         [
    //             '实体类 "Tourze\PHPStanDoctrineEntity\Tests\Data\MultiConflictEntity" 的属性 "multiConflictProperty" 同时使用了 #[IndexColumn] 和 #[ManyToOne] 属性，这些属性是互斥的，不能同时使用。',
    //             225, // line number where the property is defined in MultiConflictEntity
    //         ],
    //         [
    //             '实体类 "Tourze\PHPStanDoctrineEntity\Tests\Data\MultiConflictEntity" 的属性 "multiConflictProperty" 同时使用了 #[IndexColumn] 和 #[OneToOne] 属性，这些属性是互斥的，不能同时使用。',
    //             225, // line number where the property is defined in MultiConflictEntity
    //         ],
    //     ]);
    // }

    public function testRuleShouldProvideHelpfulErrorMessages(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        $foundConflictErrors = 0;
        foreach ($errors as $error) {
            if (str_contains($error->getMessage(), '#[IndexColumn]')) {
                ++$foundConflictErrors;

                // Each conflict error should mention both attributes
                $this->assertStringContainsString('同时使用了', $error->getMessage());
                $this->assertStringContainsString('互斥的', $error->getMessage());

                // Should provide helpful tip
                $tip = $error->getTip();
                $this->assertNotNull($tip);
                $this->assertStringContainsString('关联字段通常会自动创建外键索引', $tip);
                $this->assertStringContainsString('请从属性', $tip);
                $this->assertStringContainsString('中移除 #[IndexColumn]', $tip);

                // Should use correct identifier
                $this->assertEquals('doctrine.indexColumn.mutuallyExclusive', $error->getIdentifier());
            }
        }

        $this->assertGreaterThan(0, $foundConflictErrors, '应该找到至少一个冲突错误');
    }

    public function testRuleShouldUseShortAttributeNames(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        foreach ($errors as $error) {
            if (str_contains($error->getMessage(), '#[IndexColumn]')) {
                // Error message should use short attribute names for readability
                $message = $error->getMessage();
                $this->assertStringNotContainsString('Doctrine\ORM\Mapping\\', $message);
                $this->assertStringNotContainsString('Tourze\DoctrineIndexedBundle\Attribute\\', $message);

                // Should contain short names instead
                $this->assertStringContainsString('#[IndexColumn]', $message);
                $this->assertTrue(
                    str_contains($message, '#[ManyToOne]')
                    || str_contains($message, '#[OneToOne]')
                    || str_contains($message, '#[ManyToMany]'),
                    'Should contain at least one association attribute'
                );
            }
        }
    }

    public function testRuleShouldIgnoreNonEntityClasses(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        // RegularClassWithAttributes should not generate errors because it's not an entity
        foreach ($errors as $error) {
            $this->assertStringNotContainsString('RegularClassWithAttributes', $error->getMessage());
        }
    }

    public function testRuleShouldIgnoreValidUsage(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        // ValidIndexedEntity and ValidAssociationEntity should not generate errors
        foreach ($errors as $error) {
            $this->assertStringNotContainsString('ValidIndexedEntity', $error->getMessage());
            $this->assertStringNotContainsString('ValidAssociationEntity', $error->getMessage());
            $this->assertStringNotContainsString('EmptyEntity', $error->getMessage());
        }
    }

    public function testRuleShouldDetectMultipleConflictsInSameProperty(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        // Count conflicts for MultiConflictEntity's multiConflictProperty
        $multiConflictErrors = array_filter($errors, function ($error) {
            return str_contains($error->getMessage(), 'MultiConflictEntity')
                   && str_contains($error->getMessage(), 'multiConflictProperty')
                   && str_contains($error->getMessage(), '#[IndexColumn]');
        });

        // Should generate multiple errors for the same property
        $this->assertGreaterThan(1, count($multiConflictErrors), '应该为同一属性的多个冲突生成多个错误');
    }

    public function testRuleShouldHandleMultiplePropertiesCorrectly(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        // MultiPropertyEntity should have one error for conflictProperty but none for validProperty or validAssociation
        $multiPropertyErrors = array_filter($errors, function ($error) {
            return str_contains($error->getMessage(), 'MultiPropertyEntity');
        });

        $this->assertCount(1, $multiPropertyErrors, 'MultiPropertyEntity应该只有一个冲突错误');

        foreach ($multiPropertyErrors as $error) {
            $this->assertStringContainsString('conflictProperty', $error->getMessage());
            $this->assertStringNotContainsString('validProperty', $error->getMessage());
            $this->assertStringNotContainsString('validAssociation', $error->getMessage());
        }
    }

    public function testRuleShouldProvideLineNumbers(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/Fixtures/EntityTestFiles.php']);

        foreach ($errors as $error) {
            if (str_contains($error->getMessage(), '#[IndexColumn]')) {
                // Error should have a line number
                $this->assertGreaterThan(0, $error->getLine());
            }
        }
    }

    protected function getRule(): Rule
    {
        return new IndexColumnMutuallyExclusiveAttributesRule();
    }
}
