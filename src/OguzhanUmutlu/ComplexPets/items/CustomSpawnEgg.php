<?php

namespace OguzhanUmutlu\ComplexPets\items;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\item\SpawnEgg;
use pocketmine\math\Vector3;
use pocketmine\Player;

class CustomSpawnEgg extends SpawnEgg {
    public function onActivate(Player $player, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector): bool {
        $nbt = Entity::createBaseNBT($blockReplace->add(0.5, 0, 0.5), null, lcg_value() * 360, 0);
        $nbt->setString("petName", $this->getNamedTag()->getString("petName", "Pet"));
        $nbt->setString("petOwner", $player->getName());
        $entity = Entity::createEntity($this->getNamedTag()->getString("petType", "null"), $player->getLevelNonNull(), $nbt);
        if($entity instanceof Entity) {
            $this->pop();
            $entity->spawnToAll();
            return true;
        }
        return false;
    }
}