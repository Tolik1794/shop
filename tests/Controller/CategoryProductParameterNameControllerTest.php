<?php

namespace App\Test\Controller;

use App\Entity\CategoryProductParameterName;
use App\Repository\CategoryProductParameterNameRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CategoryProductParameterNameControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private CategoryProductParameterNameRepository $repository;
    private string $path = '/admin/category/product/parameter/name/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->repository = (static::getContainer()->get('doctrine'))->getRepository(CategoryProductParameterName::class);

        foreach ($this->repository->findAll() as $object) {
            $this->repository->remove($object, true);
        }
    }

    public function testIndex(): void
    {
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('CategoryProductParameterName index');

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
            'category_product_parameter_name[isRequired]' => 'Testing',
            'category_product_parameter_name[isFilter]' => 'Testing',
            'category_product_parameter_name[productParameterName]' => 'Testing',
            'category_product_parameter_name[category]' => 'Testing',
        ]);

        self::assertResponseRedirects('/admin/category/product/parameter/name/');

        self::assertSame($originalNumObjectsInRepository + 1, count($this->repository->findAll()));
    }

    public function testShow(): void
    {
        $this->markTestIncomplete();
        $fixture = new CategoryProductParameterName();
        $fixture->setIsRequired('My Title');
        $fixture->setIsFilter('My Title');
        $fixture->setProductParameterName('My Title');
        $fixture->setCategory('My Title');

        $this->repository->add($fixture, true);

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('CategoryProductParameterName');

        // Use assertions to check that the properties are properly displayed.
    }

    public function testEdit(): void
    {
        $this->markTestIncomplete();
        $fixture = new CategoryProductParameterName();
        $fixture->setIsRequired('My Title');
        $fixture->setIsFilter('My Title');
        $fixture->setProductParameterName('My Title');
        $fixture->setCategory('My Title');

        $this->repository->add($fixture, true);

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'category_product_parameter_name[isRequired]' => 'Something New',
            'category_product_parameter_name[isFilter]' => 'Something New',
            'category_product_parameter_name[productParameterName]' => 'Something New',
            'category_product_parameter_name[category]' => 'Something New',
        ]);

        self::assertResponseRedirects('/admin/category/product/parameter/name/');

        $fixture = $this->repository->findAll();

        self::assertSame('Something New', $fixture[0]->getIsRequired());
        self::assertSame('Something New', $fixture[0]->getIsFilter());
        self::assertSame('Something New', $fixture[0]->getProductParameterName());
        self::assertSame('Something New', $fixture[0]->getCategory());
    }

    public function testRemove(): void
    {
        $this->markTestIncomplete();

        $originalNumObjectsInRepository = count($this->repository->findAll());

        $fixture = new CategoryProductParameterName();
        $fixture->setIsRequired('My Title');
        $fixture->setIsFilter('My Title');
        $fixture->setProductParameterName('My Title');
        $fixture->setCategory('My Title');

        $this->repository->add($fixture, true);

        self::assertSame($originalNumObjectsInRepository + 1, count($this->repository->findAll()));

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertSame($originalNumObjectsInRepository, count($this->repository->findAll()));
        self::assertResponseRedirects('/admin/category/product/parameter/name/');
    }
}
