<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests\Data;

use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

// ============================================================================
// 用于 EntityTestMustExtendAbstractEntityTestRule 的测试数据
// ============================================================================

/**
 * 示例实体
 */
#[ORM\Entity]
class SampleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    public function getId(): int
    {
        return $this->id;
    }
}

/**
 * 另一个示例实体
 */
#[ORM\Entity]
class AnotherEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;
}

/**
 * 普通类（非实体）
 */
class RegularClass
{
    public function someMethod(): void
    {
    }
}

/**
 * 合法的实体测试：继承 AbstractEntityTestCase
 * @internal
 */
#[CoversClass(className: SampleEntity::class)]
final class ValidEntityTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new SampleEntity();
    }

    public function testGetIdShouldReturnCorrectValue(): void
    {
        $entity = $this->createEntity();
        $this->assertInstanceOf(SampleEntity::class, $entity);
    }
}

/**
 * 非法的实体测试：错误地继承了 TestCase 而非 AbstractEntityTestCase
 * @internal
 */
#[CoversClass(className: SampleEntity::class)]
final class InvalidEntityTest extends TestCase
{
    public function testGetIdShouldReturnCorrectValue(): void
    {
        $this->assertIsString('ok'); // 仅用于使示例测试具备断言
    }
}

/**
 * 无 CoversClass 注解的测试类
 * @internal
 * @coversNothing
 */
final class TestWithoutCoversClass extends TestCase
{
    public function testSomething(): void
    {
        $this->assertIsString('ok'); // 占位断言，避免空测试
    }
}

/**
 * 覆盖非实体类的测试
 * @internal
 */
#[CoversClass(className: RegularClass::class)]
final class RegularClassTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertIsString('ok'); // 占位断言，避免空测试
    }
}

// ============================================================================
// 用于 IndexColumnMutuallyExclusiveAttributesRule 的测试数据
// ============================================================================

/**
 * 仅使用列索引的实体（无关联）
 */
#[ORM\Entity]
class ValidIndexedEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[IndexColumn]
    #[ORM\Column(type: 'string')]
    private string $name;
}

/**
 * 仅使用关联（无 IndexColumn）的实体
 */
#[ORM\Entity]
class ValidAssociationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: SampleEntity::class)]
    #[ORM\JoinColumn(name: 'sample_entity_id', referencedColumnName: 'id')]
    private $sampleEntity;
}

/**
 * 空实体（无属性）
 */
#[ORM\Entity]
class EmptyEntity
{
}

/**
 * IndexColumn 与 ManyToOne 冲突
 */
#[ORM\Entity]
class ConflictingManyToOneEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[IndexColumn]
    #[ORM\ManyToOne(targetEntity: SampleEntity::class)]
    #[ORM\JoinColumn(name: 'sample_entity_id', referencedColumnName: 'id')]
    private $sampleEntity;
}

/**
 * IndexColumn 与 OneToOne 冲突
 */
#[ORM\Entity]
class ConflictingOneToOneEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[IndexColumn]
    #[ORM\OneToOne(targetEntity: SampleEntity::class)]
    #[ORM\JoinColumn(name: 'sample_entity_id', referencedColumnName: 'id')]
    private $sampleEntity;
}

/**
 * IndexColumn 与 ManyToMany 冲突
 */
#[ORM\Entity]
class ConflictingManyToManyEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[IndexColumn]
    #[ORM\ManyToMany(targetEntity: SampleEntity::class)]
    #[ORM\JoinTable(name: 'conflicting_sample_entities')]
    private $sampleEntities;
}

/**
 * 混合多属性：有合法也有冲突
 */
#[ORM\Entity]
class MultiPropertyEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[IndexColumn]
    #[ORM\Column(type: 'string')]
    private string $validProperty;

    #[IndexColumn]
    #[ORM\ManyToOne(targetEntity: SampleEntity::class)]
    private $conflictProperty;

    #[ORM\OneToOne(targetEntity: AnotherEntity::class)]
    private $validAssociation;
}

/**
 * 单个属性包含多个冲突
 */
#[ORM\Entity]
class MultiConflictEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[IndexColumn]
    #[ORM\ManyToOne(targetEntity: SampleEntity::class)]
    #[ORM\OneToOne(targetEntity: AnotherEntity::class)]
    private $multiConflictProperty;
}

/**
 * 普通类中包含 IndexColumn 与关联（应被忽略）
 */
class RegularClassWithAttributes
{
    #[IndexColumn]
    #[ORM\ManyToOne(targetEntity: SampleEntity::class)]
    private $property;
}
