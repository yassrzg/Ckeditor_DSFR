<?php

declare(strict_types=1);

namespace Yassrzg\CkeditorDsfrBundle\Repository;

use Yassrzg\CkeditorDsfrBundle\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function value(string $name): ?string
    {
        return $this->find($name)?->getValue();
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, ?string> valeurs des réglages enregistrés parmi $names (une seule requête)
     */
    public function values(array $names): array
    {
        $values = [];
        foreach ($this->findBy(['name' => $names]) as $setting) {
            $values[$setting->getName()] = $setting->getValue();
        }

        return $values;
    }

    public function save(string $name, ?string $value): void
    {
        $this->saveAll([$name => $value]);
    }

    /**
     * @param array<string, ?string> $values nom => valeur, enregistrés ensemble
     */
    public function saveAll(array $values): void
    {
        $existing = [];
        foreach ($this->findBy(['name' => array_keys($values)]) as $setting) {
            $existing[$setting->getName()] = $setting;
        }
        foreach ($values as $name => $value) {
            $this->getEntityManager()->persist(($existing[$name] ?? new Setting($name))->setValue($value));
        }
        $this->getEntityManager()->flush();
    }
}
