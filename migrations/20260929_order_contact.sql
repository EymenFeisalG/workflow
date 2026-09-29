-- Kontaktuppgifter hör till en enskild uppgift även när de inte sparas i kontaktregistret.
-- Kör en gång innan den uppdaterade orderkoden används.
ALTER TABLE `query`
    ADD COLUMN `contact_name` VARCHAR(225) NOT NULL DEFAULT '',
    ADD COLUMN `contact_org` VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN `contact_details` TEXT NULL;
