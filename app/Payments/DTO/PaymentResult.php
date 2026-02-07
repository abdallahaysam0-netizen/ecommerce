<?php

namespace App\Payments\DTO;

class PaymentResult
{
    public bool $success;
    public ?string $message;
    public array $data;

    public function __construct(
        bool $success,
        ?string $message = null,
        array $data = []
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
    }
}
