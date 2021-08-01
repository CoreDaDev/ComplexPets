<?php

namespace OguzhanUmutlu\ComplexPets\entities\types;

use OguzhanUmutlu\ComplexPets\entities\PetEntity;

class ParrotPet extends FlyingPetEntity {
    public const NETWORK_ID = self::PARROT;
    public $width = 0.5;
    public $height = 1.0;

    protected function initEntity(): void {
        $this->setTamed();
        parent::initEntity();
    }

    public function getName(): string {
        return "ParrotPet";
    }
}
