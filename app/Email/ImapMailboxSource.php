<?php

declare(strict_types=1);

namespace Katakata\Email;

interface ImapMailboxSource
{
    /**
     * @return list<array{
     *   id:string,
     *   from:string,
     *   to:string,
     *   subject:string,
     *   text:string,
     *   html:?string,
     *   received_at:string,
     *   attachments:list<array{id:string,name:string,media_type:string,content:string}>
     * }>
     */
    public function fetch(ImapSettings $settings, int $limit = 100): array;
}
