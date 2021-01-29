<?php

namespace Tamkeen\Musaned\eVisa\Events;

use Tamkeen\Musaned\ContractIndonesia\ContractIndonesia\ContractIndonesia;

class contractIndonesiaRequested
{
    /**
     * @var \Tamkeen\Musaned\ContractIndonesia\ContractIndonesia\ContractIndonesia
     */
    private $contractIndonesia;

    /**
     * @param \Tamkeen\Musaned\ContractIndonesia\ContractIndonesia\ContractIndonesia $contractIndonesia
     */
    public function __construct(ContractIndonesia $contractIndonesia)
    {
        $this->contractIndonesia = $contractIndonesia;
    }

    /**
     * @return contractIndonesia
     */
    public function getIssueRequest()
    {
        return $this->contractIndonesia;
    }
}
