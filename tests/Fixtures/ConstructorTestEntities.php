<?php

declare(strict_types=1);

namespace Tourze\PHPStanDoctrineEntity\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

// 正确的Entity - 无参数构造函数
#[ORM\Entity]
#[ORM\Table(name: 'valid_entity')]
class ValidEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    public function __construct()
    {
        // 无参数构造函数 - 正确
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

// 正确的Entity - 没有构造函数
#[ORM\Entity]
#[ORM\Table(name: 'no_constructor_entity')]
class NoConstructorEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;
}

// 错误的Entity - 带参数的构造函数
#[ORM\Entity]
#[ORM\Table(name: 'invalid_entity')]
class InvalidEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $category;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

// 错误的Entity - 带多个参数的构造函数
#[ORM\Entity]
#[ORM\Table(name: 'multi_param_entity')]
class MultiParameterEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $category;

    public function __construct(string $name, string $category)
    {
        $this->name = $name;
        $this->category = $category;
    }
}

// 非Entity类 - 带参数构造函数（应该被忽略）
class NonEntityWithConstructor
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
