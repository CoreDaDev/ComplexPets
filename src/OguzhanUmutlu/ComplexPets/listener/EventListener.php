<?php

namespace OguzhanUmutlu\ComplexPets\listener;

use OguzhanUmutlu\ComplexPets\entities\PetEntity;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;

class EventListener implements Listener {
    public function onRightClickEntity(DataPacketReceiveEvent $event) {
        $pk = $event->getPacket();
        $player = $event->getPlayer();
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