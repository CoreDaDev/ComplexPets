<?php

namespace OguzhanUmutlu\ComplexPets;

use BadMethodCallException;
use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\element\Dropdown;
use muqsit\invmenu\InvMenuHandler;
use OguzhanUmutlu\ComplexPets\entities\PetEntity;
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
    /*** @var PetEntity[] */
    private static $petsSpawned = [];
    public const DEFAULT_PETS = [
        "WolfPet" => [
            "class" => WolfPet::class,
            "UiName" => "Wolf",
            "permission" => "wolf"
        ],
        "DogPet" => [
            "class" => DogPet::class,
            "UiName" => "Dog",
            "permission" => "dog"
        ]
    ];
    private static $pets = [];
    public function onEnable() {
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        foreach(self::DEFAULT_PETS as $name => $pet)
            self::registerPet($name, $pet["class"], $pet["UiName"], $pet["permission"]);
        if(!InvMenuHandler::isRegistered())
            InvMenuHandler::register($this);
    }

    /**
     * @param PetEntity $pet
     * @internal Only internal uses.
     */
    public static function addSpawnedPet(PetEntity $pet): void {
        self::$petsSpawned[] = $pet;
    }

    public static function registerPet(string $name, string $className, string $UiName, string $permission): void {
        if(isset(self::$pets[$name])) throw new BadMethodCallException("Pet named " . $name . " already exists!");
        self::$pets[$name] = [
            "class" => $className,
            "UiName" => $UiName,
            "permission" => $permission
        ];
        Entity::registerEntity($className, true, [$name]);
    }

    public static function unregisterPet(string $name): void {
        if(!isset(self::$pets[$name])) throw new BadMethodCallException("Pet named " . $name . " doesn't exists!");
        foreach(self::$petsSpawned as $pet)
            if(!$pet->isClosed() && get_class($pet) == self::$pets[$name]["class"])
                $pet->flagForDespawn();
        unset(self::$pets[$name]);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if($command->getName() != "pets" || !$sender->hasPermission($command->getPermission()))
            return true;
        if($sender instanceof Player) {
            $names = array_values(
                array_map(function($n){return $n["UiName"];}, array_filter(self::$pets, function($j) use ($sender) {
                    return $sender->hasPermission("complex"."pets.pets.cmd.".$j["permission"]);
                }))
            );
            $sender->sendForm(new CustomForm(
                "Get pet",
                [
                    new Dropdown("pets", "Select pet type", $names, 0)
                ],
                function(Player $player, CustomFormResponse $response) use ($names): void {
                    $index = array_search($names[$response->getInt("pets")],
                        array_map(function($n){
                            return $n["UiName"];
                        }, self::$pets)
                    );
                    if(!is_string($index) || strlen($index) < 1) {
                        $player->sendMessage("§c> An error occurred while getting pet!");
                        return;
                    }
                    if(!$player->hasPermission(self::$pets[$index]["permission"])) {
                        $player->sendMessage("§c> You don't have permission to use this command!");
                        return;
                    }
                    Entity::createEntity($index, $player->level, new PetNBT($player, $player))->spawnToAll();
                }
            ));
        }
        return true;
    }
}