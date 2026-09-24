<?php

function cleanInput(?string $value): string
{
    return trim($value ?? '');
}


function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function isValidEmail(string $email): bool
{
    return filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    ) !== false;
}


function isValidId($id): bool
{
    return filter_var(
        $id,
        FILTER_VALIDATE_INT
    ) !== false && $id > 0;
}