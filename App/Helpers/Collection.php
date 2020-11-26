<?php
declare(strict_types=1);

namespace App\Helpers;

use ArrayObject;

class Collection extends ArrayObject
{

    public function mapByType()
    {

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