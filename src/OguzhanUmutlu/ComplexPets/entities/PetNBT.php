<?php

namespace OguzhanUmutlu\ComplexPets\entities;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;

class PetNBT extends CompoundTag {
    public function __construct(Vector3 $pos, Player $player, ?Vector3 $motion = null, float $yaw = 0.0, float $pitch = 0.0) {
        $nbt = Entity::createBaseNBT($pos, $motion, $yaw, $pitch);
        $nbt->setString("petOwner", $player->getName());
        parent::__construct($nbt->getName(), $nbt->getValue());
    }
}