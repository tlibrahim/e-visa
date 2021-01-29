<?php

namespace Tamkeen\Musaned\tests\eVisa\Services\MLSD\Laborer;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Tamkeen\Musaned\eVisa\Services\MLSD\Exceptions\LaborNotFoundException;
use Tamkeen\Musaned\eVisa\Services\MLSD\Exceptions\MLSDConnectionException;
use Tamkeen\Musaned\eVisa\Services\MLSD\Laborer\MLSDLaborerJSONService;
use Tamkeen\Musaned\tests\eVisa\TestCase;

class MLSDLaborerServiceTest extends TestCase
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    public function setUp()
    {
        parent::setUp();
        $this->client = $this->prophesize(Client::class);
    }

    /** @test */
    public function should_throw_exception_when_connection_fails()
    {
        $this->client->post(Argument::cetera())->willThrow(new Exception());
        $this->setExpectedException(MLSDConnectionException::class);

        $service = new MLSDLaborerJSONService($this->client->reveal());
        $service->getLaborerInfoByLaborerId('2020202020');
    }

    /** @test */
    public function should_throw_exception_if_labor_not_found()
    {
        $mockedResponse = config('mlsd.data');
        $this->client->post(Argument::cetera())->willReturn(
            new Response(
                200,
                ['content-type' => 'application/json'],
                json_encode($mockedResponse)
            )
        );
        $this->setExpectedException(LaborNotFoundException::class);

        $service = new MLSDLaborerJSONService($this->client->reveal());
        $service->getLaborerInfoByLaborerId('2020202020');
    }

    /** @test */
    public function should_return_labor_if_exist()
    {
        $data = config('mlsd.data');
        $ids = array_keys($data);
        $response = [
            'GetLaborersInfoResult' => [
                'LaborerInformation' => $data[$ids[0]],
            ],
        ];

        $this->client->post(Argument::cetera())->willReturn(
            new Response(
                200,
                ['content-type' => 'application/json'],
                json_encode($response)
            )
        );

        $service = new MLSDLaborerJSONService($this->client->reveal());
        $labor = $service->getLaborerInfoByLaborerId($ids[0]);

        $this->assertNotNull($labor);
    }
}
