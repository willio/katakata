<?php

declare(strict_types=1);

use Katakata\Content\Author;
use Katakata\Content\Post;
use Katakata\View;

$root = dirname(__DIR__, 3);
require $root . '/bootstrap/helpers.php';
require $root . '/app/Content/Author.php';
require $root . '/app/Content/Post.php';
require $root . '/app/View.php';

$post = static fn (string $slug, string $title, string $date, ?string $author = null): Post => new Post(
    slug: $slug,
    title: $title,
    date: new DateTimeImmutable($date),
    author: $author,
    tags: [],
    excerpt: null,
    status: 'published',
    body: '',
    meta: [],
    path: "/tmp/{$slug}.md",
);

$lead = $post('lead', 'Di Antara Kota dan Ingatan', '2026-08-10', 'will');
$recent = [
    $post('recent-1', 'Membaca Kembali Kota', '2026-08-08'),
    $post('recent-2', 'Catatan dari Sebuah Perjalanan', '2026-08-04'),
    $post('recent-3', 'Bahasa yang Tinggal', '2026-07-29'),
    $post('recent-4', 'Rumah, Jarak, dan Waktu', '2026-07-21'),
    $post('recent-5', 'Percakapan di Ujung Hari', '2026-07-12'),
    $post('recent-6', 'Arsip untuk Masa Depan', '2026-07-03'),
];
$earlierThisYear = [
    '2026-06' => [
        $post('june-1', 'Tentang Hujan yang Tidak Kunjung Selesai di Selatan Kota', '2026-06-24'),
        $post('june-2', 'Kota Tanpa Peta', '2026-06-17'),
        $post('june-3', 'Surat dari Selatan', '2026-06-09'),
        $post('june-4', 'Pagi yang Lambat', '2026-06-01'),
    ],
    '2026-05' => [
        $post('may-1', 'Halaman Belakang', '2026-05-22'),
        $post('may-2', 'Nama-Nama Jalan yang Berubah Ketika Kita Pulang', '2026-05-14'),
        $post('may-3', 'Suara dari Dapur', '2026-05-03'),
    ],
];

echo (new View($root . '/resources/views'))->render('home', [
    'name' => 'Katakata',
    'tagline' => '',
    'siteUrl' => 'http://127.0.0.1',
    'lead' => $lead,
    'leadAuthor' => new Author('will', 'Will', null, null, [], ''),
    'recent' => $recent,
    'earlierThisYear' => $earlierThisYear,
    'archiveYear' => '2025',
]);
