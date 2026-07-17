<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Attributes;

use Attribute;
use Elavora\Api\Framework\Contracts\RequestValidatorAttribute;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use Elavora\Api\Framework\Validation\ValidationRule;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Declara campos obrigatorios no corpo da request.
 *
 * Aceita uma lista simples de nomes ou um mapa nome => regra.
 * Regras podem ser tipos nomeados, DataTypes ou validadores callable.
 *
 * Exemplo: #[RequiredFields(['name' => 'string', 'email' => 'email'])]
 */
final class RequiredFields implements RequestValidatorAttribute
{
    /**
     * @param array<int|string, mixed> $fields Campos obrigatorios. Use ['campo'] para aceitar qualquer valor
     *                                        ou ['campo' => 'string'] para validar tipo/regra.
     */
    public function __construct(private readonly array $fields)
    {
    }

    /**
     * Valida os campos obrigatorios no corpo JSON/form da request.
     *
     * @return Response|null Retorna null quando os campos sao validos, ou resposta 400 com erros por campo.
     */
    public function validate(Request $request): ?Response
    {
        $errors = [];

        foreach ($this->fields as $field => $rule) {
            if (is_int($field)) {
                $field = (string) $rule;
                $rule = 'mixed';
            }

            $value = $request->input((string) $field);
            if ($value === null) {
                $errors[$field] = 'Field not found';
                continue;
            }

            if ($rule !== 'mixed' && !ValidationRule::validate($value, $rule)) {
                $errors[$field] = 'Invalid field type';
            }
        }

        if ($errors === []) {
            return null;
        }

        return Response::json(payload: ['message' => 'Invalid fields', 'errors' => ['fields' => $errors]], status: 400);
    }

    /**
     * @return array{fields: array<string, string>} Metadados dos campos obrigatorios.
     */
    public function options(): array
    {
        return ['fields' => $this->describeFields()];
    }

    private function describeFields(): array
    {
        $fields = [];
        foreach ($this->fields as $field => $rule) {
            if (is_int($field)) {
                $fields[(string) $rule] = 'mixed';
                continue;
            }

            $fields[(string) $field] = ValidationRule::describe($rule);
        }

        return $fields;
    }
}


