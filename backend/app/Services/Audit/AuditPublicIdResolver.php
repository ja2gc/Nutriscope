<?php

namespace App\Services\Audit;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class AuditPublicIdResolver
{
    public function forModel(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $publicId = $model->getAttribute('uuid');
        if (! is_string($publicId)
            && in_array(HasPublicId::class, class_uses_recursive($model), true)
            && $model->exists
            && $model->getKey() !== null) {
            $publicId = $model->newQueryWithoutScopes()->whereKey($model->getKey())->value('uuid');
        }

        return is_string($publicId) && Uuid::isValid($publicId) ? strtolower($publicId) : null;
    }
}
