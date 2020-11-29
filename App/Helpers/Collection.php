<?php
declare(strict_types=1);

namespace App\Helpers;

use ArrayObject;

class Collection extends ArrayObject
{

    /**
     * Return array mapped by type
     *
     * @return array
     */
    public function mapByType(): array
    {
        $toReturn = [];
        foreach ($this->getArrayCopy() as $item) {
            $toReturn[$item['type']][] = $item;
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

        return count($importance) !== 0 ? array_sum($importance) / count($importance) : 0;
    }
}