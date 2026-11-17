<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateDish;

use App\Catalog\Domain\Model\Dish;
use App\Catalog\Domain\Repository\DishRepository;
use App\Catalog\Domain\Service\IngredientReferenceGuard;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Exception\ValidationException;
use Psr\Clock\ClockInterface;

final readonly class CreateDishHandler implements CommandHandler
{
    public function __construct(
        private DishRepository $dishes,
        private IngredientReferenceGuard $guard,
        private TransactionalRunner $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Same order and transaction boundary as CreateComponentHandler: the
     * dish's own rules first, then the references, then the insert.
     *
     * @throws ValidationException DSH-01..03, ING-01..03
     */
    public function __invoke(CreateDish $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $dish = Dish::create($command->id, $command->name, $command->lines, $this->clock->now());
            $this->guard->assertValidForDish($command->lines);
            $this->dishes->add($dish);
        });
    }
}
