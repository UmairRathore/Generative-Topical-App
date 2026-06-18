<?php

namespace App\Models\Concerns;

use App\Support\Hashid;

/*
|--------------------------------------------------------------------------
| HasHashid — route ids are encoded short codes instead of integers
|--------------------------------------------------------------------------
| getRouteKey() emits the encoded code (so route($model) and {model} links are
| automatically hashed); resolveRouteBinding() decodes it back. Raw integer ids
| are still tolerated so any internal/legacy link keeps resolving.
*/
trait HasHashid
{
    public function getRouteKey()
    {
        return Hashid::encode($this->getKey());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $id = Hashid::decode((string) $value);
        if ($id === null && ctype_digit((string) $value)) {
            $id = (int) $value;
        }
        if ($id === null) {
            return null;
        }

        return $this->where($field ?? $this->getKeyName(), $id)->first();
    }
}
