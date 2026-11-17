<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateProduct;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\TransactionalRunner;
use App\Shared\Domain\Exception\ValidationException;
use Psr\Clock\ClockInterface;

/**
 * PRD-02 (unique name) is not checked here: a check before the insert would
 * race with a concurrent request, so it is the database's UNIQUE constraint,
 * turned into a 409 when the Doctrine repository arrives (D-07).
 */
final readonly class CreateProductHandler implements CommandHandler
{
    public function __construct(
        private ProductRepository $products,
        private TransactionalRunner $transaction,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ValidationException PRD-01
     */
    public function __invoke(CreateProduct $command): void
    {
        $this->transaction->run(function () use ($command): void {
            $this->products->add(Product::create($command->id, $command->name, $command->category, $this->clock->now()));
        });
    }
}
