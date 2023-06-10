<?php

namespace App\Test\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private ArticleRepository $repository;
    private string $path = '/article/api/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->repository = static::getContainer()->get('doctrine')->getRepository(Article::class);

        foreach ($this->repository->findAll() as $object) {
            $this->repository->remove($object, true);
        }
    }

    public function testIndex(): void
    {
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Article index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first());
    }

    public function testNew(): void
    {
        $originalNumObjectsInRepository = count($this->repository->findAll());

        $this->markTestIncomplete();
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'article[title]' => 'Testing',
            'article[short]' => 'Testing',
            'article[content]' => 'Testing',
            'article[thumbnail]' => 'Testing',
            'article[publishedAt]' => 'Testing',
            'article[updatedAt]' => 'Testing',
            'article[comments]' => 'Testing',
            'article[publishedBy]' => 'Testing',
            'article[updatedBy]' => 'Testing',
        ]);

        self::assertResponseRedirects('/article/api/');

        self::assertSame($originalNumObjectsInRepository + 1, count($this->repository->findAll()));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new Article();
        $fixture->setTitle('My Title');
        $fixture->setShort('My Title');
        $fixture->setContent('My Title');
        $fixture->setThumbnail('My Title');
        $fixture->setPublishedAt('My Title');
        $fixture->setUpdatedAt('My Title');
        $fixture->setComments('My Title');
        $fixture->setPublishedBy('My Title');
        $fixture->setUpdatedBy('My Title');

        $this->repository->save($fixture, true);

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Article');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new Article();
        $fixture->setTitle('My Title');
        $fixture->setShort('My Title');
        $fixture->setContent('My Title');
        $fixture->setThumbnail('My Title');
        $fixture->setPublishedAt('My Title');
        $fixture->setUpdatedAt('My Title');
        $fixture->setComments('My Title');
        $fixture->setPublishedBy('My Title');
        $fixture->setUpdatedBy('My Title');

        $this->repository->save($fixture, true);

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'article[title]' => 'Something New',
            'article[short]' => 'Something New',
            'article[content]' => 'Something New',
            'article[thumbnail]' => 'Something New',
            'article[publishedAt]' => 'Something New',
            'article[updatedAt]' => 'Something New',
            'article[comments]' => 'Something New',
            'article[publishedBy]' => 'Something New',
            'article[updatedBy]' => 'Something New',
        ]);

        self::assertResponseRedirects('/article/api/');

        $fixture = $this->repository->findAll();

        self::assertSame('Something New', $fixture[0]->getTitle());
        self::assertSame('Something New', $fixture[0]->getShort());
        self::assertSame('Something New', $fixture[0]->getContent());
        self::assertSame('Something New', $fixture[0]->getThumbnail());
        self::assertSame('Something New', $fixture[0]->getPublishedAt());
        self::assertSame('Something New', $fixture[0]->getUpdatedAt());
        self::assertSame('Something New', $fixture[0]->getComments());
        self::assertSame('Something New', $fixture[0]->getPublishedBy());
        self::assertSame('Something New', $fixture[0]->getUpdatedBy());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();

        $originalNumObjectsInRepository = count($this->repository->findAll());

        $fixture = new Article();
        $fixture->setTitle('My Title');
        $fixture->setShort('My Title');
        $fixture->setContent('My Title');
        $fixture->setThumbnail('My Title');
        $fixture->setPublishedAt('My Title');
        $fixture->setUpdatedAt('My Title');
        $fixture->setComments('My Title');
        $fixture->setPublishedBy('My Title');
        $fixture->setUpdatedBy('My Title');

        $this->repository->save($fixture, true);

        self::assertSame($originalNumObjectsInRepository + 1, count($this->repository->findAll()));

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertSame($originalNumObjectsInRepository, count($this->repository->findAll()));
        self::assertResponseRedirects('/article/api/');
    }
}
