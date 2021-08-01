<?php

namespace OguzhanUmutlu\ComplexPets\entities;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Toggle;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use dktapps\pmforms\ModalForm;
use Exception;
use muqsit\invmenu\inventory\InvMenuInventory;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use OguzhanUmutlu\ComplexPets\ComplexPets;
use OguzhanUmutlu\ComplexPets\inventory\PetInventory;
use OguzhanUmutlu\ComplexPets\items\CustomSpawnEgg;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\block\Solid;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\level\particle\AngryVillagerParticle;
use pocketmine\level\particle\HeartParticle;
use pocketmine\math\Vector3;
use pocketmine\math\VoxelRayTrace;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;

abstract class PetEntity extends Living {
    /*** @var PetEntity[] */
    public static $riders = [];
    public $riderYaw = 0;

    protected $jumpVelocity = 0.6;

    public $riding = false;
    public $isBaby = false;
    private $canOwnerSee = true;
    private $canOthersSee = true;
    public $owner = "";
    /*** @var int */
    private $clientMoveTicks;

    public function getOwner(): ?Player {
        return Server::getInstance()->getPlayerExact($this->owner);
    }

    public function isSitting(): bool {
        return $this->getDataFlag(self::DATA_FLAGS, self::DATA_FLAG_SITTING);
    }

    public function setSitting(bool $value): void {
        $this->setDataFlag(self::DATA_FLAGS, self::DATA_FLAG_SITTING, $value);
    }

    /*** @var PetInventory */
    public $inventory;

    /*** @return PetInventory */
    public function getInventory(): PetInventory {
        return $this->inventory;
    }

    /*** @param bool $canOwnerSee */
    public function setCanOwnerSee(bool $canOwnerSee): void {
        if($this->getOwner() instanceof Player) {
            $this->despawnFrom($this->getOwner());
            if($canOwnerSee)
                $this->spawnTo($this->getOwner());
        }
        $this->canOwnerSee = $canOwnerSee;
    }

    /*** @param bool $canOthersSee */
    public function setCanOthersSee(bool $canOthersSee): void {
        if($canOthersSee) {
            $this->despawnFromAll();
            $this->spawnToAll();
            if(!$this->canOwnerSee && $this->getOwner() instanceof Player)
                $this->despawnFrom($this->getOwner());
        } else {
            foreach ($this->getViewers() as $viewer)
                if ($viewer->getName() != $this->owner)
                    $this->despawnFrom($viewer);
        }
        $this->canOthersSee = $canOthersSee;
    }

    /*** @param bool $isBaby */
    public function setIsBaby(bool $isBaby): void {
        $this->isBaby = $isBaby;
        $this->setGenericFlag(self::DATA_FLAG_BABY, $isBaby);
    }

    protected function initEntity(): void {
        $this->setIsBaby($this->namedtag->getByte("isSitting", false));
        $this->canOwnerSee = $this->namedtag->getByte("canOwnerSee", true);
        $this->canOthersSee = $this->namedtag->getByte("canOthersSee", true);
        $this->setSitting($this->namedtag->getByte("isSitting", false));
        $this->owner = $this->namedtag->getString("petOwner");
        $this->inventory = new PetInventory($this);
        if($this->namedtag->hasTag("PetInventory")) {
            $tag = $this->namedtag->getListTag("PetInventory");
            foreach($tag->getValue() as $compound)
                if($compound instanceof CompoundTag) {
                    $item = Item::nbtDeserialize($compound);
                    $slot = $compound->getInt("ItemSlot");
                    $this->getInventory()->setItem($slot, $item);
                }
        }
        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();
        $this->setNameTag("§e".$this->namedtag->getString("petName", "Pet")."\n§aPet owner: ".$this->owner);
        parent::initEntity();
    }

    private function onRiderMount(Player $entity) : void {
        $entity->getDataPropertyManager()->setByte(self::DATA_RIDER_ROTATION_LOCKED, 1);
        $entity->getDataPropertyManager()->setFloat(self::DATA_RIDER_MAX_ROTATION, 90.0);
        $entity->getDataPropertyManager()->setFloat(self::DATA_RIDER_MIN_ROTATION, 0.0);
        $entity->getDataPropertyManager()->setVector3(self::DATA_RIDER_SEAT_POSITION, new Vector3(0, 2, 0));
        $this->riding = true;
        self::$riders[$entity->getName()] = $this;
    }

    public function onRiderLeave(Player $entity) : void {
        $entity->getDataPropertyManager()->setByte(self::DATA_RIDER_ROTATION_LOCKED, 0);
        $entity->getDataPropertyManager()->setFloat(self::DATA_RIDER_MAX_ROTATION, 360.0);
        $entity->getDataPropertyManager()->setFloat(self::DATA_RIDER_MIN_ROTATION, 0.0);
        $this->riding = false;
        unset(self::$riders[$entity->getName()]);
    }

    public function linkPlayerToPet(Player $player = null, int $type = EntityLink::TYPE_RIDER) : void{
        foreach($this->getViewers() as $viewer) {
            if (!isset($viewer->getViewers()[$player->getLoaderId()]))
                $player->spawnTo($viewer);
            $pk = new SetActorLinkPacket();
            $pk->link = new EntityLink($this->getId(), $player->getId(), $type, false, true);
            $viewer->sendDataPacket($pk);
        }
        $this->onRiderMount($player);
    }
    public $swimTicks = 0;
    public function onUpdate(int $currentTick): bool {
        $owner = $this->getOwner();
        $hasUpdate = parent::onUpdate($currentTick);
        if($this->isClosed()) {
            return $hasUpdate;
        }
        if(!$owner instanceof Player || !$owner->isOnline() || $owner->isClosed()) {
            $this->setInvisible();
            return true;
        }
        $this->setInvisible(false);
        if($this->riding && !$this->getOwner() instanceof Player || $this->getOwner()->isClosed())
            $this->riding = false;
        if($this->ticksLived % 100 == 0 && $this->riding)
            $this->linkPlayerToPet($this->getOwner());
        if($this->riding) {
            return $hasUpdate;
        }
        if($this->level->getId() == $owner->level->getId()) {
            if($this->distance($owner) > 15 && $this->getOwner()->isOnGround())
                $this->teleport($owner);
            else {
                $this->lookAt($owner->add(0, $owner->eyeHeight/2));
                if($this->distance($owner) < 1.8 || $this->isSitting())
                    return $hasUpdate;
                $direction = $this->getDirectionVector()->multiply($this->isUnderwater() ? 0.1 : 0.2);
                $firstBlock = $this->getTargetBlockCopy($this->yaw, 0, 0.5, 1.5);
                $secondBlock = $this->getTargetBlockCopy($this->yaw, 0, 1.5, 1.5);
                if($firstBlock instanceof Solid && $secondBlock instanceof Solid)
                    return $hasUpdate;
                if(!$secondBlock instanceof Solid && $firstBlock instanceof Solid)
                    $this->jump();
                if($this->getOwner()->y > $this->y && $this->isUnderwater()) {
                    if($this->level->getBlock($this->add(0, 1)) instanceof Liquid) {
                        if($this->swimTicks >= 15)
                            $this->setMotion(new Vector3(0, 0.1, 0));
                        else $this->swimTicks++;
                    } else {
                        if($this->swimTicks >= 25)
                            $this->setMotion(new Vector3(0, 0.3, 0));
                        else $this->swimTicks++;
                    }
                }
                $this->move($direction->x, 0, $direction->z);
            }
        }
        return $hasUpdate;
    }

    public function kill(): void {
        if($this->level)
            foreach($this->inventory->getContents() as $item)
                $this->level->dropItem($this, $item);
        parent::kill();
    }

    public function close(): void {
        $this->inventory->clearAll();
        foreach($this->inventory->menus as $menu)
            $menu->getInventory()->clearAll();
        $owner = $this->getOwner();
        if($owner instanceof Player && !$owner->isClosed() && $owner->isOnline() && isset(self::$riders[$owner->getName()])) {
            $pet = self::$riders[$owner->getName()];
            if($pet->getId() == $this->getId())
                unset(self::$riders[$owner->getName()]);
        }
        parent::close();
    }

    public function attack(EntityDamageEvent $source): void {
        if(!$source instanceof EntityDamageByEntityEvent || $source instanceof EntityDamageByChildEntityEvent) return;
        $player = $source->getDamager();
        if(!$player instanceof Player) return;
        if($player->getName() != $this->owner) {
            $player->sendMessage("§c> You cannot manage others' pets!");
            return;
        }
        $player->sendForm(
            new MenuForm(
                "Pet Menu",
                "Select an action:",
                [
                    new MenuOption("Set pet's name"),
                    new MenuOption("Open pet's inventory"),
                    new MenuOption("Get pet as spawn egg"),
                    new MenuOption("Change pet's visibilities"),
                    new MenuOption("Make pet ".[true => "stand up", false => "sit down"][$this->isSitting()]),
                    new MenuOption("Remove pet")
                ],
                function(Player $player, int $response): void {
                    if($player->isClosed() || $this->isClosed() || !$player->level || !$this->level || $player->level->getId() != $this->level->getId()) return;
                    switch($response) {
                        case 0:
                            $player->sendForm(new CustomForm(
                                "Pet Menu > Set pet's name",
                                [
                                    new Input("name", "Pet's name", "Pet", $this->namedtag->getString("petName", "Pet"))
                                ],
                                function(Player $player, CustomFormResponse $response): void {
                                    $this->namedtag->setString("petName", $response->getString("name"));
                                    $this->setNameTag("§e".$this->namedtag->getString("petName", "Pet")."\n§aPet owner: ".$this->owner);
                                    $player->sendMessage("§a> Pet's name changed");
                                }
                            ));
                            break;
                        case 1:
                            $id = count($this->inventory->menus);
                            $menu = InvMenu::create(InvMenu::TYPE_CHEST);
                            $menu->setListener(function (InvMenuTransaction $transaction) use ($menu) {
                                ComplexPets::$instance->getScheduler()->scheduleDelayedTask(new class($menu, $this) extends Task {
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
                                }, 1);
                                return $transaction->continue();
                            })
                                ->setInventoryCloseListener(function (Player $player, InvMenuInventory $inventory) use ($id) {
                                    unset($this->inventory->menus[$id]);
                                });
                            $this->getInventory()->menus[] = $menu;
                            $menu->getInventory()->setContents($this->inventory->getContents());
                            $menu->setName("Pet's inventory");
                            $menu->send($player);
                            break;
                        case 2:
                            $item = new CustomSpawnEgg();
                            $item->getNamedTag()->setString("petType", $this->getName());
                            $item->getNamedTag()->setString("petName", $this->namedtag->getString("petName", "Pet"));
                            if($player->getInventory()->canAddItem($item)) {
                                $player->getInventory()->addItem($item);
                                $this->destroy(true, false);
                                $player->sendMessage("§a> Pet successfully converted to spawn egg!");
                            } else $player->sendMessage("§c> You don't have enough space in your inventory!");
                            break;
                        case 3:
                            $player->sendForm(new CustomForm(
                                "Pet Menu > Change pet's visibilities",
                                [
                                    new Toggle("owner", "Can owner see pet?", $this->canOwnerSee),
                                    new Toggle("others", "Can others see pet?", $this->canOthersSee)
                                ],
                                function(Player $player, CustomFormResponse $response): void {
                                    $this->setCanOwnerSee($response->getBool("owner"));
                                    $this->setCanOthersSee($response->getBool("others"));
                                }
                            ));
                            break;
                        case 4:
                            $this->setSitting(!$this->isSitting());
                            $player->sendMessage("§e> Pet is now ".[true => "§c"."sitting down", false => "§a"."standing up"][$this->isSitting()]."§e.");
                            break;
                        case 5:
                            $player->sendForm(new ModalForm(
                                "Pet Menu > Remove pet",
                                "You cannot revert this action!\nDo you want to remove pet?",
                                function(Player $player, bool $response): void {
                                    if($response) {
                                        $this->destroy();
                                        $player->sendMessage("§c> Pet is removed.");
                                    }
                                }
                            ));
                            break;
                    }
                }
            )
        );
    }

    /**
     * @param bool $smoke
     * @param bool $heart
     * @throws Exception
     */
    public function destroy(bool $smoke = true, bool $heart = true): void {
        if($this->level)
            foreach($this->inventory->getContents() as $item)
                $this->level->dropItem($this, $item);
        $this->flagForDespawn();
        if($smoke)
            for($i=0;$i<5;$i++)
                $this->level->addParticle(new AngryVillagerParticle($this->add(random_int(-15, 15)/15, random_int(-10, 15)/15, random_int(-15, 15)/15)));
        if($heart)
            $this->level->addParticle(new HeartParticle($this->add(0, 1.5), 3));
    }

    public function saveNBT(): void {
        $this->namedtag->setByte("isSitting", $this->isSitting());
        $this->namedtag->setByte("isBaby", $this->isBaby);
        parent::saveNBT();
    }

    protected function applyGravity(): void {
        $this->motion->y -= $this->isUnderwater() ? $this->gravity/4 : $this->gravity;
    }

    // classic api

    public function getTargetBlockCopy(float $yaw, float $pitch, float $eyeHeight, int $maxDistance = 50): ?Block {
        $line = $this->getLineOfSightCopy($yaw, $pitch, $eyeHeight, $maxDistance);
        if(count($line) > 0)
            return array_shift($line);
        return null;
    }
    public function getLineOfSightCopy(float $yaw, float $pitch, float $eyeHeight, int $maxDistance = 50): array {
        $blocks = [];
        $nextIndex = 0;
        foreach(VoxelRayTrace::inDirection($this->add(0, $eyeHeight), $this->getDirectionVectorCopy($yaw, $pitch), $maxDistance) as $vector3){
            $block = $this->level->getBlockAt($vector3->x, $vector3->y, $vector3->z);
            $blocks[$nextIndex++] = $block;
            if(count($blocks) > 1){
                array_shift($blocks);
                --$nextIndex;
            }
            if($block instanceof Solid)
                break;
        }
        return $blocks;
    }
    public function lookAtCopyYaw(Vector3 $target, ?Vector3 $from = null): float {
        $from = $from ?? $this;
        $xDist = $target->x - $from->x;
        $zDist = $target->z - $from->z;
        $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
        if($yaw < 0)
            $yaw += 360.0;
        return $yaw;
    }
    public function lookAtCopyPitch(Vector3 $target, ?Vector3 $from = null): float {
        $from = $from ?? $this;
        $horizontal = sqrt(($target->x - $from->x) ** 2 + ($target->z - $from->z) ** 2);
        $vertical = $target->y - $from->y;
        return -atan2($vertical, $horizontal) / M_PI * 180;
    }
    public function getDirectionVectorCopy(float $yaw, float $pitch): Vector3 {
        $y = -sin(deg2rad($pitch));
        $xz = cos(deg2rad($pitch));
        $x = -$xz * sin(deg2rad($yaw));
        $z = $xz * cos(deg2rad($yaw));
        return $this->temporalVector->setComponents($x, $y, $z)->normalize();
    }
    public function getLookingRate(Entity $entity, int $eyeHeight = 0): float {
        $yaw = $this->lookAtCopyYaw($entity->add(0, -$eyeHeight))["yaw"];
        $pitch = $this->lookAtCopyPitch($entity->add(0, -$eyeHeight))["pitch"];
        $yawRate = abs($yaw-$this->yaw);
        $pitchRate = abs($pitch-$this->pitch);
        return ($yawRate+$pitchRate)/2;
    }
    public function jump(): void {
        if($this->onGround || $this->level->getBlock($this) instanceof Liquid)
            $this->motion->y = $this->getJumpVelocity();
    }
}