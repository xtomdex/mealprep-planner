<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateComponent;

use App\Catalog\Domain\Model\Component;
use App\Catalog\Domain\Repository\ComponentRepository;
use App\Catalog\Domain\Service\IngredientReferenceGuard;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Exception\ConflictException;
use App\Shared\Domain\Exception\ValidationException;
use Psr\Clock\ClockInterface;

final readonly class CreateComponentHandler implements CommandHandler
{
    public function __construct(
        private ComponentRepository $components,
        private IngredientReferenceGuard $guard,
        private TransactionalRunner $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The aggregate is built first: its own rules need no reads, so a
     * malformed recipe fails before the repositories are asked anything.
     * The reference check and the insert share one transaction, so the
     * products and components the check saw are the ones the write relies on.
     *
     * @throws ValidationException CMP-01..04, ING-01..04
     * @throws ConflictException   CMP-05, raised by the database through the runner
     */
    public function __invoke(CreateComponent $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $component = Component::create($command->id, $command->name, $command->yieldPortions, $command->lines, $this->clock->now());
            $this->guard->assertValidForComponent($command->id, $command->lines);
            $this->components->add($component);
        });
    }
}
