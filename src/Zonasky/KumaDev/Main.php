<?php

declare(strict_types=1);

namespace Zonasky\KumaDev;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\item\Item;

class Main extends PluginBase implements Listener {

    private $worlds;
    private $config;

    public function onLoad(): void {
        $this->saveDefaultConfig();
    }

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->loadConfig();
    }

    private function loadConfig(): void {
        $this->config = $this->getConfig();
        $this->worlds = $this->config->get("worlds", []);
    }

    private function isTimberWorld(string $worldName): bool {
        return in_array($worldName, $this->worlds);
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $worldName = $block->getPosition()->getWorld()->getFolderName();
        if (!$this->isTimberWorld($worldName)) {
            return;
        }

        if ($this->isLogBlock($block)) {
            $treeBlocks = $this->getTreeBlocks($block);
            $world = $block->getPosition()->getWorld();
            $leaves = [];
            foreach ($treeBlocks as $treeBlock) {
                // Prevent default drops by removing them before breaking the block
                $event->setDrops([]);
                // Manually set the block to air without triggering drops
                $world->setBlock($treeBlock->getPosition(), VanillaBlocks::AIR(), false);
                $leaves = array_merge($leaves, $this->getLeavesBlocks($treeBlock));
            }

            $leavesConfig = $this->config->get("leaves");
            if ($leavesConfig) {
                foreach ($leaves as $leaf) {
                    // Manually set the leaf block to air without triggering drops
                    $world->setBlock($leaf->getPosition(), VanillaBlocks::AIR(), false);
                }
            }

            // Check autopickup option in the configuration
            if ($this->config->get("autopickup", true)) {
                // Add items directly to the player's inventory from tree blocks
                foreach ($treeBlocks as $treeBlock) {
                    if ($treeBlock instanceof Block) {
                        try {
                            $drops = $treeBlock->getDrops($player->getInventory()->getItemInHand());
                            foreach ($drops as $drop) {
                                $player->getInventory()->addItem($drop);
                            }
                        } catch (\Exception $e) {
                            $this->getLogger()->error("Error adding items to player inventory: " . $e->getMessage());
                        }
                    }
                }

                // Limit the number of leaf drops added to the player's inventory
                $maxLeafDrops = $this->config->get("maxLeafDrops", 10);
                $leafDropsAdded = 0;
                foreach ($leaves as $leafBlock) {
                    if ($leafBlock instanceof Block && $leafDropsAdded < $maxLeafDrops) {
                        try {
                            $drops = $leafBlock->getDrops($player->getInventory()->getItemInHand());
                            foreach ($drops as $drop) {
                                if ($leafDropsAdded < $maxLeafDrops) {
                                    $player->getInventory()->addItem($drop);
                                    $leafDropsAdded++;
                                } else {
                                    break 2; // Exit both foreach loops
                                }
                            }
                        } catch (\Exception $e) {
                            $this->getLogger()->error("Error adding items to player inventory: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    private function isLogBlock(Block $block): bool {
        $blockTypeId = $block->getTypeId();
        return $blockTypeId == VanillaBlocks::OAK_LOG()->getTypeId()
            || $blockTypeId == VanillaBlocks::SPRUCE_LOG()->getTypeId()
            || $blockTypeId == VanillaBlocks::BIRCH_LOG()->getTypeId()
            || $blockTypeId == VanillaBlocks::JUNGLE_LOG()->getTypeId()
            || $blockTypeId == VanillaBlocks::ACACIA_LOG()->getTypeId()
            || $blockTypeId == VanillaBlocks::DARK_OAK_LOG()->getTypeId();
    }

    private function isLeafBlock(Block $block): bool {
        $blockTypeId = $block->getTypeId();
        return $blockTypeId == VanillaBlocks::OAK_LEAVES()->getTypeId()
            || $blockTypeId == VanillaBlocks::SPRUCE_LEAVES()->getTypeId()
            || $blockTypeId == VanillaBlocks::BIRCH_LEAVES()->getTypeId()
            || $blockTypeId == VanillaBlocks::JUNGLE_LEAVES()->getTypeId()
            || $blockTypeId == VanillaBlocks::ACACIA_LEAVES()->getTypeId()
            || $blockTypeId == VanillaBlocks::DARK_OAK_LEAVES()->getTypeId();
    }

    private function getTreeBlocks(Block $block): array {
        $world = $block->getPosition()->getWorld();
        $blocks = new \SplQueue();
        $blocks->enqueue($block);
        $visited = [$block];

        while (!$blocks->isEmpty()) {
            $current = $blocks->dequeue();
            for ($y = $current->getPosition()->getY() - 1; $y <= $current->getPosition()->getY() + 1; $y++) {
                $blockAtPos = $world->getBlock(new Vector3($current->getPosition()->getX(), $y, $current->getPosition()->getZ()));
                if ($this->isLogBlock($blockAtPos) && !in_array($blockAtPos, $visited)) {
                    $blocks->enqueue($blockAtPos);
                    $visited[] = $blockAtPos;
                }
            }
        }

        return $visited;
    }

    private function getLeavesBlocks(Block $block): array {
        $world = $block->getPosition()->getWorld();
        $blocks = new \SplQueue();
        $blocks->enqueue($block);
        $visited = [$block];

        while (!$blocks->isEmpty()) {
            $current = $blocks->dequeue();
            for ($x = $current->getPosition()->getX() - 1; $x <= $current->getPosition()->getX() + 1; $x++) {
                for ($y = $current->getPosition()->getY() - 1; $y <= $current->getPosition()->getY() + 1; $y++) {
                    for ($z = $current->getPosition()->getZ() - 1; $z <= $current->getPosition()->getZ() + 1; $z++) {
                        $leaf = $world->getBlock(new Vector3($x, $y, $z));
                        if ($this->isLeafBlock($leaf) && !in_array($leaf, $visited)) {
                            $blocks->enqueue($leaf);
                            $visited[] = $leaf;
                        }
                    }
                }
            }
        }

        return $visited;
    }
}
