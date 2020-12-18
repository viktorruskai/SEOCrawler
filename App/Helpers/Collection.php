<?php
declare(strict_types=1);

namespace App\Helpers;

use ArrayObject;

class Collection extends ArrayObject
{

    /**
     * Return array mapped by type
     *
     * @param string $field
     * @return array
     */
    public function mapBy(string $field = 'type'): array
    {
        $toReturn = [];
        foreach ($this->getArrayCopy() as $item) {
            $toReturn[$item[$field]][] = $item;
        }

        return $toReturn;
    }

    /**
     * Average importance
     *
     * @return float
     */
    public function averageImportance(): float
    {
        $importance = array_map(static function($item) {
            return $item['importance'] ?? 0;
        }, $this->getArrayCopy());

        return count($importance) !== 0 ? round(array_sum($importance) / count($importance), 1) : 0;
    }
}