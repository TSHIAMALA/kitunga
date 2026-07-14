<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707091338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE commission (id INT AUTO_INCREMENT NOT NULL, level INT NOT NULL, percentage NUMERIC(5, 2) NOT NULL, amount NUMERIC(10, 2) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, order_id INT NOT NULL, beneficiary_id INT NOT NULL, buyer_id INT NOT NULL, INDEX IDX_1C6501588D9F6D38 (order_id), INDEX IDX_1C650158ECCAAFA0 (beneficiary_id), INDEX IDX_1C6501586C755722 (buyer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commission_payment (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(10, 2) NOT NULL, payment_method VARCHAR(50) NOT NULL, transaction_reference VARCHAR(100) DEFAULT NULL, paid_at DATETIME NOT NULL, beneficiary_id INT NOT NULL, recorded_by_id INT NOT NULL, INDEX IDX_C4FD62DDECCAAFA0 (beneficiary_id), INDEX IDX_C4FD62DDD05A957B (recorded_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commission_wallet (id INT AUTO_INCREMENT NOT NULL, available_balance NUMERIC(10, 2) NOT NULL, total_generated NUMERIC(10, 2) NOT NULL, total_paid NUMERIC(10, 2) NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_152011F3A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(100) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT NOT NULL, type VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `order` (id INT AUTO_INCREMENT NOT NULL, subtotal NUMERIC(10, 2) NOT NULL, delivery_fee NUMERIC(10, 2) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, delivery_address LONGTEXT NOT NULL, payment_method VARCHAR(50) NOT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, customer_id INT NOT NULL, deliverer_id INT DEFAULT NULL, INDEX IDX_F52993989395C3F3 (customer_id), INDEX IDX_F5299398B6A6A3F4 (deliverer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, order_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_52EA1F098D9F6D38 (order_id), INDEX IDX_52EA1F094584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, price NUMERIC(10, 2) NOT NULL, image_url VARCHAR(255) DEFAULT NULL, is_available TINYINT NOT NULL, stock INT NOT NULL, created_at DATETIME NOT NULL, category_id INT NOT NULL, INDEX IDX_D34A04AD12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, slug VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_CDFC73565E237E06 (name), UNIQUE INDEX UNIQ_CDFC7356989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(100) NOT NULL, phone VARCHAR(20) NOT NULL, address LONGTEXT DEFAULT NULL, referral_code VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, referrer_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D649444F97DD (phone), UNIQUE INDEX UNIQ_8D93D6496447454A (referral_code), INDEX IDX_8D93D649798C22DB (referrer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
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
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649798C22DB FOREIGN KEY (referrer_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
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
        $this->addSql('DROP TABLE commission');
        $this->addSql('DROP TABLE commission_payment');
        $this->addSql('DROP TABLE commission_wallet');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_category');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
