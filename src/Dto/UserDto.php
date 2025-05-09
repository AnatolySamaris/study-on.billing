<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserDto
{
    #[Assert\NotBlank(message: "Email is mandatory")]
    #[Assert\Email(message: "Invalid email address")]
    public string $email;
    

    #[Assert\NotBlank(message: "Password is mandatory")]
    #[Assert\Length(
        min: 6,
        minMessage: "Password must consist of at least {{ limit }} symbols"
    )]
    public string $password;
}
