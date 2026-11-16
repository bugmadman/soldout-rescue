<?php

declare(strict_types=1);

namespace App\Infrastructure\DataPack;

use App\Domain\Money\HistoricalRecords;

interface DataPackReaderInterface
{
    public function read(DataPackDirectory $directory): HistoricalRecords;
}
