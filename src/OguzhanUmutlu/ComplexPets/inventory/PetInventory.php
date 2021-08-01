<?php

namespace OguzhanUmutlu\ComplexPets\inventory;

use muqsit\invmenu\InvMenu;
use OguzhanUmutlu\ComplexPets\entities\PetEntity;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Server;

class PetInventory extends BaseInventory implements Inventory {
    /*** @var PetEntity */
    public $pet;
    /*** @var InvMenu[] */
    public $menus = [];
    public $lastTick = 0;
    public function __construct(PetEntity $pet, array $items = [], int $size = null, string $title = null) {
        parent::__construct($items, $size, $title);
        $this->pet = $pet;
    }

    public function getName(): string {
        return "PetInventory";
    }

    public function getDefaultSize(): int {
        return 27;
    }

    public function saveInventory(): void {
        if(!$this->pet instanceof PetEntity || $this->pet->isClosed()) return;
        $items = [];
        foreach($this->getContents() as $slot => $nbt) {
            $item = $nbt->nbtSerialize();
            $item->setInt("ItemSlot", $slot);
            $items[] = $item;
        }
        $this->pet->namedtag->setTag(new ListTag("PetInventory", $items));
        $this->pet->saveNBT();
    }

    public function setContents(array $items, bool $send = true): void {
        parent::setContents($items, $send);
        foreach($this->menus as $menu)
            $menu->getInventory()->setContents($this->getContents());
        $this->saveInventory();
        $this->lastTick = Server::getInstance()->getTick();
    }

    public function setItem(int $index, Item $item, bool $send = true): bool {
        $res = parent::setItem($index, $item, $send);
        foreach($this->menus as $menu)
            $menu->getInventory()->setContents($this->getContents());
        $this->saveInventory();
        $this->lastTick = Server::getInstance()->getTick();
        return $res;
    }

    public function removeItem(Item ...$slots): array {
        $res = parent::removeItem(...$slots);
        foreach($this->menus as $menu)
            $menu->getInventory()->setContents($this->getContents());
        $this->saveInventory();
        $this->lastTick = Server::getInstance()->getTick();
        return $res;
    }

    public function remove(Item $item): void {
        foreach($this->menus as $menu)
            $menu->getInventory()->setContents($this->getContents());
        $this->saveInventory();
        $this->lastTick = Server::getInstance()->getTick();
    }
}