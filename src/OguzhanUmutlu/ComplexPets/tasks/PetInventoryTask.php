<?php

namespace OguzhanUmutlu\ComplexPets\tasks;

use muqsit\invmenu\InvMenu;
use OguzhanUmutlu\ComplexPets\ComplexPets;
use OguzhanUmutlu\ComplexPets\entities\PetEntity;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class PetInventoryTask extends Task {
    public $menu;
    public $entity;

    public function __construct(InvMenu $menu, PetEntity $entity) {
        $this->menu = $menu;
        $this->entity = $entity;
    }

    public function onRun(int $currentTick) {
        $menu = $this->menu;
        $entity = $this->entity;
        if ($entity->inventory->lastTick == Server::getInstance()->getTick()) {
            ComplexPets::$instance->getScheduler()->scheduleDelayedTask(new self($menu, $entity), 1);
        } else $entity->getInventory()->setContents($menu->getInventory()->getContents());
    }
}