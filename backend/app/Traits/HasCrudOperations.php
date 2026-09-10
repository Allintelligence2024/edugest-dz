<?php
namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait HasCrudOperations
{
    protected function performStore(array $data, array $relations = []): mixed
    {
        return DB::transaction(function () use ($data, $relations) {
            $model = $this->model::create($data);

            foreach ($relations as $relation => $values) {
                if (method_exists($model, $relation)) {
                    $model->$relation()->sync($values);
                }
            }

            return $model->load($this->with);
        });
    }

    protected function performUpdate(mixed $model, array $data, array $relations = []): mixed
    {
        return DB::transaction(function () use ($model, $data, $relations) {
            $model->update($data);

            foreach ($relations as $relation => $values) {
                if (method_exists($model, $relation)) {
                    $model->$relation()->sync($values);
                }
            }

            return $model->fresh($this->with);
        });
    }

    protected function performDelete(mixed $model, bool $hard = false): bool
    {
        return DB::transaction(function () use ($model, $hard) {
            if ($hard) {
                return $model->forceDelete();
            }
            return $model->delete();
        });
    }

    protected function uploadFile($file, string $folder, string $disk = 'public'): string
    {
        $tenantId = config('tenant.current_id', 'global');
        return $file->store("{$folder}/{$tenantId}", $disk);
    }

    protected function toggleStatut(mixed $model, string $field = 'statut', array $allowed = ['actif', 'inactif']): mixed
    {
        $current = $model->$field;
        $next    = $current === $allowed[0] ? $allowed[1] : $allowed[0];
        $model->update([$field => $next]);
        return $model->fresh();
    }
}
