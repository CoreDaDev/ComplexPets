<?php

namespace OguzhanUmutlu\ComplexPets\listener;

use OguzhanUmutlu\ComplexPets\entities\PetEntity;
use pocketmine\block\Solid;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerInputPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\Server;

class EventListener implements Listener {
    public $cool = [];
    public function onRightClickEntity(DataPacketReceiveEvent $event) {
        $pk = $event->getPacket();
        $player = $event->getPlayer();
        if($pk instanceof PlayerInputPacket) {
            $pet = PetEntity::$riders[$player->getName()] ?? null;
            if($pet instanceof PetEntity && $pet->owner == $player->getName())
                if($pk->motionX == 0 && $pk->motionY == 1) {
                    $pet->yaw = $pet->riderYaw;
                    $player->yaw = $pet->riderYaw;
                    $direction = $pet->getDirectionVector();
                    $firstBlock = $pet->getTargetBlockCopy($pet->yaw, 0, 0.5, 1.5);
                    $secondBlock = $pet->getTargetBlockCopy($pet->yaw, 0, 1.5, 1.5);
                    if(!$secondBlock instanceof Solid && $firstBlock instanceof Solid)
                        $pet->jump();
                    if(($this->cool[$player->getName()] ?? 0) > Server::getInstance()->getTick()) return;
                    $this->cool[$player->getName()] = Server::getInstance()->getTick()+2;
                    $pet->move($direction->x, 0, $direction->z);
                }
        }
        if($pk instanceof MovePlayerPacket) {
            $pet = PetEntity::$riders[$player->getName()] ?? null;
            if($pet instanceof PetEntity && $pet->owner == $player->getName())
                $pet->riderYaw = $pk->headYaw;
        }
        if($pk instanceof InteractPacket && $pk->action == InteractPacket::ACTION_LEAVE_VEHICLE) {
            $entity = $player->level->getEntity($pk->target);
            if($entity instanceof PetEntity)
                if($entity->owner == $player->getName())
                    $entity->onRiderLeave($player);
        }
        if($pk instanceof InventoryTransactionPacket) {
            $trData = $pk->trData;
            if($trData instanceof UseItemOnEntityTransactionData && $trData->getActionType() != UseItemOnEntityTransactionData::ACTION_ATTACK) {
                $entity = $player->level->getEntity($trData->getEntityRuntimeId());
                if($entity instanceof PetEntity) {
                    if($entity->owner == $player->getName())
                        $entity->linkPlayerToPet($player);
                }
            }
        }
    }
}