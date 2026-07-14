<?php

namespace App\Services\Reports\Instances;

use App\Services\Reports\Contracts\InstanceSource;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Entity axis: each matching record is a renderable instance (a received PO, a
 * budget, a menu cycle, a patient). The base query already encodes "has data"
 * (e.g. only received POs, only patients with a meal plan), so hasData() just
 * checks the keyed record still satisfies it.
 */
class EntityInstanceSource implements InstanceSource
{
    /**
     * @param  Closure():Builder  $query  A fresh base query over renderable records.
     * @param  string  $paramKey  Param the generator reads (e.g. 'budget_id').
     * @param  Closure(Model):string  $label  Human label for an instance.
     * @param  ?string  $dateColumn  Optional date for sort + year/month facets.
     */
    public function __construct(
        private Closure $query,
        private string $paramKey,
        private Closure $label,
        private ?string $dateColumn = null,
    ) {}

    public function axis(): string
    {
        return 'entity';
    }

    public function instances(array $filters): array
    {
        $query = ($this->query)();
        $query = $this->dateColumn
            ? $query->orderByDesc($this->dateColumn)
            : $query->orderByDesc($query->getModel()->getKeyName());

        $year = ! empty($filters['year']) ? (int) $filters['year'] : null;
        $month = ! empty($filters['month']) ? (int) $filters['month'] : null;

        return $query->get()
            ->map(function (Model $model) {
                $date = $this->dateColumn ? $model->{$this->dateColumn} : null;

                return [
                    'key' => (string) $model->getKey(),
                    'label' => ($this->label)($model),
                    'params' => [$this->paramKey => $model->getKey()],
                    'date' => $date ? Carbon::parse($date)->toDateString() : null,
                ];
            })
            ->filter(function (array $i) use ($year, $month) {
                if ($year === null && $month === null) {
                    return true;
                }
                if (! $i['date']) {
                    return false;
                }
                $d = Carbon::parse($i['date']);

                return ($year === null || $d->year === $year)
                    && ($month === null || $d->month === $month);
            })
            ->values()
            ->all();
    }

    public function hasData(array $params): bool
    {
        $id = $params[$this->paramKey] ?? null;

        return $id !== null && ($this->query)()->whereKey($id)->exists();
    }
}
