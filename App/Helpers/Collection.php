<?php
declare(strict_types=1);

namespace App\Helpers;

use ArrayObject;

class Collection extends ArrayObject
{

    /**
     * Return array mapped by type
     *
     * @param string|null $field
     * @param string|null $sortBy
     * @param int|null $order
     * @return array
     */
    public function mapBy(?string $field = null, ?string $sortBy = null, ?int $order = null): array
    {
        $arrayCollection = $this->getArrayCopy();

        if ($sortBy) {
            $columns = array_column($arrayCollection, $sortBy);

            if (!in_array($order, [SORT_ASC, SORT_DESC], true)) {
                $order = SORT_DESC;
            }

            array_multisort($columns, $order, $arrayCollection);
        }

        if ($field) {
            $toReturn = [];

            foreach ($arrayCollection as $item) {
                $toReturn[$item[$field]][] = $item;
            }

            $arrayCollection = $toReturn;
        }

        return $arrayCollection;
    }

    /**
     * Average importance
     *
     * @return float
     */
    public function averageImportance(): float
    {
        $importance = array_map(static function ($item) {
            return $item['importance'] ?? 0;
        }, $this->getArrayCopy());

        return count($importance) !== 0 ? round(array_sum($importance) / count($importance), 1) : 0;
    }
}