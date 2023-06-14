<?php

namespace Opencart\Admin\Model\Extension\Lingva\Module;
class Lingva extends \Opencart\System\Engine\Model {

  public function install(): void {

    $this->db->query("CREATE TABLE IF NOT EXISTS  `" . DB_PREFIX . "lingva_product` (
                                                  `lingva_product_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                                                  `product_id` INT(11) NOT NULL,
                                                    PRIMARY KEY (`lingva_product_id`),
                                                    UNIQUE INDEX `product_id_UNIQUE` (`product_id` ASC) VISIBLE)");
  }

  public function unInstall(): void {

    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "lingva_product`");
  }

  public function translateProduct(int $product_id): int {

    $this->db->query("INSERT INTO `" . DB_PREFIX . "lingva_product` SET `product_id`  = " . (int) $product_id);

    return $this->db->getLastId();
  }

  public function updateProductDescription(int $product_id, int $language_id, string $name, string $description): void {

    $this->db->query("UPDATE `" . DB_PREFIX . "product_description` SET   `name`        = '" . $this->db->escape($name) . "',
                                                                          `description` = '" . $this->db->escape($description) . "'

                                                                    WHERE `product_id`  = " . (int) $product_id . "
                                                                      AND `language_id` = " . (int) $language_id);

  }

  public function getProducts(array $category_ids, int $language_id): array {

    $query = $this->db->query("SELECT `pd`.`product_id`,
                                      `pd`.`name`,
                                      `pd`.`description` FROM `" . DB_PREFIX . "product_description` AS `pd`
                                                         JOIN `" . DB_PREFIX . "product_to_category` AS `p2c` ON (`p2c`.`product_id` = `pd`.`product_id`)

                                                         WHERE `p2c`.`category_id` IN (" . implode(",", $category_ids) . ")
                                                           AND `pd`.`language_id` = " . (int) $language_id . "
                                                           AND `pd`.`product_id` NOT IN (SELECT `lp`.`product_id` FROM `" . DB_PREFIX . "lingva_product` AS `lp`)

                                                         LIMIT 1");

    return $query->rows;
  }

  public function getTotalProducts(array $category_ids): int {

    $query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "product` AS `p`
                                                          JOIN `" . DB_PREFIX . "product_to_category` AS `p2c` ON (`p2c`.`product_id` = `p`.`product_id`)

                                                          WHERE `p2c`.`category_id` IN (" . implode(",", $category_ids) . ")");

    return $query->row['total'];
  }

  public function getTotalProductsTranslated(): int {

    $query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "lingva_product`");

    return $query->row['total'];
  }

  public function deleteProduct(int $product_id): void {

    $this->db->query("DELETE FROM `" . DB_PREFIX . "lingva_product` WHERE `product_id` = '" . (int) $product_id . "' LIMIT 1");
  }
}
