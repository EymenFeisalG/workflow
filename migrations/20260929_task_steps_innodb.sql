-- Step updates and notifications must commit or roll back together.
ALTER TABLE `steps` ENGINE = InnoDB;
