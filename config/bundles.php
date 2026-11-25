<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    // Keeps one connection per test run and wraps each test in a transaction
    // that is rolled back, so database tests never see each other's rows.
    DAMA\DoctrineTestBundle\DAMADoctrineTestBundle::class => ['test' => true],
];
