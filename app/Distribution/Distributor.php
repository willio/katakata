<?php

declare(strict_types=1);

namespace Katakata\Distribution;

use Katakata\Content\Post;

final class Distributor
{
    /** @param list<Adapter> $adapters */
    public function __construct(private readonly array $adapters)
    {
    }

    /** @return list<Delivery> */
    public function distribute(Post $post, ?string $channel = null): array
    {
        $deliveries = [];

        foreach ($this->adapters as $adapter) {
            if ($channel !== null && $adapter->channel() !== $channel) {
                continue;
            }

            try {
                $deliveries[] = Delivery::delivered($adapter->channel(), $adapter->distribute($post));
            } catch (\Throwable $error) {
                $deliveries[] = Delivery::failed($adapter->channel(), $error);
            }
        }

        return $deliveries;
    }
}
