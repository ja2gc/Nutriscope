<?php

namespace App\Http\Requests\Concerns;

/**
 * Frontend pickers now submit a model's public uuid (its Resource's 'id' field,
 * per HasPublicId) instead of the internal bigint PK. Requests that accept a
 * route-bound model as a plain foreign key in the body (not via route binding)
 * resolve that uuid back to the internal id here, right after validation, so
 * every downstream ->input()/->validated() read already sees the integer FK.
 */
trait ResolvesUuidForeignKeys
{
    /** @return array<string,class-string> field name => model class */
    abstract protected function uuidForeignKeyMap(): array;

    protected function passedValidation(): void
    {
        $resolved = [];
        foreach ($this->uuidForeignKeyMap() as $field => $modelClass) {
            if ($this->filled($field)) {
                $resolved[$field] = $modelClass::idFromUuid($this->input($field));
            }
        }
        if ($resolved !== []) {
            $this->merge($resolved);
        }
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);
        if (is_array($data)) {
            foreach (array_keys($this->uuidForeignKeyMap()) as $field) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = $this->input($field);
                }
            }
        }
        return $data;
    }
}
