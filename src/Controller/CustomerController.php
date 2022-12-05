<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Service\JsonEntityHelper;
use App\Service\RestPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;

use Nelmio\ApiDocBundle\Annotation as Nelmio;
use OpenApi\Attributes as OA;

class CustomerController extends AbstractController
{
    private const CACHE_CUSTOMERS = "cacheCustomers";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security               $security,
        private readonly JsonEntityHelper       $jsonHelper
    ) {}

    /**
     * Fetch all of your customers (paginated response).
     */
    #[Nelmio\Areas(["default"]), OA\Tag(name: "Customers")]
    #[OA\Parameter(name: "page", description: "The page of the result", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1))]
    #[OA\Parameter(name: "limit", description: "Number of elements per page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 3))]
    #[OA\Response(response: 200, description: "Your paginated customers list", content: new OA\JsonContent(type: "array", items: new OA\Items(ref: new Model(type: Customer::class, groups: ["getCustomers"]))))]
    #[Route('/api/customers', name: 'api_customers', methods: ['GET'], format: 'json')]
    public function getAllCustomers(
        Request                 $request,
        CustomerRepository      $customerRepo,
        TagAwareCacheInterface  $cachePool,
        RestPaginator           $paginator,
    ) : JsonResponse
    {
        $usrId = $this->security->getUser()->getId();

        // Create the customers count cache if needed
        $customerCountCacheId = "getAllCustomers-count-$usrId";
        $customerCount = $cachePool->get($customerCountCacheId,
            function (ItemInterface $item) use ($usrId, $customerRepo) {
                $item->tag(self::CACHE_CUSTOMERS)->expiresAfter(60);
                return $customerRepo->getCountOfUser($usrId);
        });

        // Create the customers list cache if needed, depending on the current user id and the query parameters (page, limit)
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $getCustomersCacheId = "getAllCustomers-$usrId-$page-$limit";
        $jsonCustomersList = $cachePool->get($getCustomersCacheId,
            function (ItemInterface $item) use ($usrId, $customerRepo, $page, $limit, $customerCount, $paginator) {
                $item->tag(self::CACHE_CUSTOMERS)->expiresAfter(60);
                $list = $customerRepo->findOfUserWithPagination($usrId, $page, $limit);

                $pages = $paginator->asArray("api_customers", $page, $limit, $customerCount);
                return $this->jsonHelper->serialize(array("_pages" => $pages, "items" => $list), ["getCustomers"]);
        });

        return new JsonResponse(data: $jsonCustomersList, status: Response::HTTP_OK, json: true);
    }

    /**
     * Fetch one of your customer.
     */
    #[Nelmio\Areas(["default"]), OA\Tag(name: "Customers")]
    #[OA\PathParameter(name: "id", description: "The id of your customer", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "The customer data is in the response body", content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["getCustomers"])))]
    #[Route('/api/customers/{id}', name: 'api_customer_get', methods: ['GET'], format: 'json')]
    public function fetchCustomer(Customer $customer): JsonResponse
    {
        $this->checkUserRightsOnCustomer($customer);

        $jsonCustomer = $this->jsonHelper->serialize($customer, ["getCustomers"]);
        return new JsonResponse(data: $jsonCustomer, status: Response::HTTP_OK, json: true);
    }

    /**
     * Create a new customer for your company.
     */
    #[Nelmio\Areas(["default"]), OA\Tag(name: "Customers")]
    #[OA\RequestBody(description: "Create a new customer object", required: true, content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["modifyCustomers"])))]
    #[OA\Response(response: 201, description: "Your customer has been created", content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["getCustomers"])))]
    #[Route('/api/customers/', name: 'api_customer_create', methods: ['POST'], format: 'json')]
    public function createCustomer(
        Request                $request,
        UrlGeneratorInterface  $urlGenerator,
        TagAwareCacheInterface $cachePool
    ) : JsonResponse
    {
        $customer = $this->jsonHelper->deserializeAndValidate($request->getContent(), Customer::class, ["modifyCustomers"]);
        $customer->setUser($this->getUser());
        $customer->setCreationDate(new \DateTimeImmutable());

        $this->em->persist($customer);
        $this->em->flush();

        $cachePool->invalidateTags([self::CACHE_CUSTOMERS]);

        // Return the created customer as serialized JSON
        $jsonCustomer = $this->jsonHelper->serialize($customer, ["getCustomers"]);
        $location = $urlGenerator->generate('api_customer_get', ['id' => $customer->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Update one of your customer.
     */
    #[Nelmio\Areas(["default"]), OA\Tag(name: "Customers")]
    #[OA\RequestBody(description: "Update an existing customer", required: true, content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["modifyCustomers"])))]
    #[OA\PathParameter(name: "id", description: "The id of the customer to update", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 204, description: "Your customer has been updated")]
    #[Route('/api/customers/{id}', name: 'api_customer_update', methods: ['PUT'], format: 'json')]
    public function updateCustomer(
        Request                $request,
        Customer               $customer,
        TagAwareCacheInterface $cachePool,
        EntityManagerInterface $em
    ) : JsonResponse
    {
        $this->checkUserRightsOnCustomer($customer);

        $customer = $this->jsonHelper->updateEntity($request->getContent(), $customer, ["modifyCustomers"]);

        $em->persist($customer);
        $em->flush();

        // We modified customers collection, invalidate cache
        $cachePool->invalidateTags([self::CACHE_CUSTOMERS]);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete one of your customer.
     */
    #[OA\Tag(name: "Customers")]
    #[OA\PathParameter(name: "id", description: "The id of your customer", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 204, description: "Your customer has been deleted")]
    #[Route('/api/customers/{id}', name: 'api_customer_delete', methods: ['DELETE'], format: 'json')]
    public function deleteCustomer(Customer $customer, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $this->checkUserRightsOnCustomer($customer);

        $this->em->remove($customer);
        $this->em->flush();

        $cachePool->invalidateTags([self::CACHE_CUSTOMERS]);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    private function checkUserRightsOnCustomer(Customer $customer) : void
    {
        if($customer->getUser()->getId() !== $this->security->getUser()->getId()) {
            throw new AccessDeniedHttpException();
        }
    }
}