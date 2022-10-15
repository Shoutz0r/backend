<?php

namespace App\Exceptions;

use \Exception;
use Throwable;

/**
 * This exception is thrown when a form has failed validation.
 */
class FormValidationException extends Exception
{
    /**
     * Contains the fields that have failed validation
     * @var FormValidationFieldError[]
     */
    private array $errors;

    /**
     * @param FormValidationFieldError[] $errors
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(array $errors, int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Form Validation failed, see $errors for details', $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Returns the fields that have failed validation
     * @return FormValidationFieldError[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
