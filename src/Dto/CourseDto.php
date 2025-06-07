<?php

namespace App\Dto;

use App\Enum\CourseType;
use Symfony\Component\Validator\Constraints as Assert;

class CourseDto
{
    #[Assert\Choice(choices: [CourseType::FREE->value, CourseType::PAY->value, CourseType::RENT->value])]
    public int $type;

    #[Assert\NotBlank(message: "Title is mandatory")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Max course title length is {{ limit }} symbols"
    )]
    public string $title;

    #[Assert\NotBlank(message: "Code is mandatory")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Max course code length is {{ limit }} symbols"
    )]
    public string $code;

    #[Assert\GreaterThanOrEqual(0)]
    public float|null $price = null;
}
