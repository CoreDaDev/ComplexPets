<?php

namespace OguzhanUmutlu\ComplexPets\entities\types;

use OguzhanUmutlu\ComplexPets\entities\PetEntity;

class WolfPet extends PetEntity {
    public const NETWORK_ID = self::WOLF;
    public $width = 0.5;
    public $height = 0.5;
    public function getName(): string {
        return "WolfPet";
    }
}