<?php

namespace OguzhanUmutlu\ComplexPets;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Dropdown;
use muqsit\invmenu\InvMenuHandler;
use OguzhanUmutlu\ComplexPets\entities\PetNBT;
use OguzhanUmutlu\ComplexPets\entities\types\DogPet;
use OguzhanUmutlu\ComplexPets\entities\types\WolfPet;
use OguzhanUmutlu\ComplexPets\listener\EventListener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class ComplexPets extends PluginBase {
    /*** @var ComplexPets|null */
    public static $instance = null;
    public function onEnable() {
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        Entity::registerEntity(WolfPet::class, true, ["WolfPet"]);
        Entity::registerEntity(DogPet::class, true, ["DogPet"]);
        if(!InvMenuHandler::isRegistered())
            InvMenuHandler::register($this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if($command->getName() != "pets" || !$sender->hasPermission($command->getPermission()))
            return true;
        if($sender instanceof Player) {
            $sender->sendForm(new CustomForm(
                "Get pet",
                [
                    new Dropdown("pets", "Select pet type", ["Wolf", "Dog"], 0)
                ],
                function(Player $player, CustomFormResponse $response): void {
                    Entity::createEntity([
                        "WolfPet",
                        "DogPet"
                    ][$response->getInt("pets")], $player->level, new PetNBT($player, $player))->spawnToAll();

                }
            ));
        }
        return true;
    }
}