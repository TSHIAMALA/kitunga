<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715185159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE stock_movement (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, type VARCHAR(30) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, product_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_BB1BC1B54584665A (product_id), INDEX IDX_BB1BC1B5B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B54584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stock_movement ADD CONSTRAINT FK_BB1BC1B5B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE commission ADD CONSTRAINT FK_1C6501588D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commission ADD CONSTRAINT FK_1C650158ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE commission ADD CONSTRAINT FK_1C6501586C755722 FOREIGN KEY (buyer_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE commission_payment ADD CONSTRAINT FK_C4FD62DDECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE commission_payment ADD CONSTRAINT FK_C4FD62DDD05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE commission_wallet ADD CONSTRAINT FK_152011F3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398B6A6A3F4 FOREIGN KEY (deliverer_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F098D9F6D38 FOREIGN KEY (order_id) REFERENCES `order` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE product ADD CONSTRAINT FK_D34A04AD12469DE2 FOREIGN KEY (category_id) REFERENCES product_category (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649798C22DB FOREIGN KEY (referrer_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B54584665A');
        $this->addSql('ALTER TABLE stock_movement DROP FOREIGN KEY FK_BB1BC1B5B03A8386');
        $this->addSql('DROP TABLE stock_movement');
        $this->addSql('ALTER TABLE commission DROP FOREIGN KEY FK_1C6501588D9F6D38');
        $this->addSql('ALTER TABLE commission DROP FOREIGN KEY FK_1C650158ECCAAFA0');
        $this->addSql('ALTER TABLE commission DROP FOREIGN KEY FK_1C6501586C755722');
        $this->addSql('ALTER TABLE commission_payment DROP FOREIGN KEY FK_C4FD62DDECCAAFA0');
        $this->addSql('ALTER TABLE commission_payment DROP FOREIGN KEY FK_C4FD62DDD05A957B');
        $this->addSql('ALTER TABLE commission_wallet DROP FOREIGN KEY FK_152011F3A76ED395');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398B6A6A3F4');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F098D9F6D38');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04AD12469DE2');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649798C22DB');
    }
}
