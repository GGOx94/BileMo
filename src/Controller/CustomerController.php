<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Smartphone;
use App\Exception\ApiValidationException;
use App\Repository\CustomerRepository;
use App\Service\RestPaginator;
use Doctrine\ORM\EntityManagerInterface;
use http\Exception\RuntimeException;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Exception\ValidationFailedException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class CustomerController extends AbstractController
{
    private const CACHE_CUSTOMERS = "cacheCustomers";
    private SerializationContext $serializationContext;

    public function __construct(private readonly Security $security, private readonly CustomerRepository $customerRepo)
    {
        $this->serializationContext = SerializationContext::create()->setGroups(['getCustomers']);
    }

    /**
     * Fetch all of your customers (paginated response).
     */
    #[OA\Parameter(name: "page", description: "The page of the result", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1))]
    #[OA\Parameter(name: "limit", description: "Number of elements per page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 3))]
    #[OA\Response(response: 200, description: "Your paginated customers, as a JSON array", content: new OA\JsonContent(type: "array", items: new OA\Items(ref: new Model(type: Customer::class, groups: ["getCustomers"]))))]
    #[OA\Tag(name: "Customers")]
    #[Route('/api/customers/', name: 'api_customers', methods: ['GET'], format: 'json')]
    public function getAllCustomers(
        Request                 $request,
        SerializerInterface     $serializer,
        TagAwareCacheInterface  $cachePool,
        RestPaginator           $paginator,
    ) : JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $usrId = $this->security->getUser()->getId();

        $customerCountCacheId = "getCustomers-count-$usrId";
        $customerCount = $cachePool->get($customerCountCacheId, function (ItemInterface $item) use ($usrId)
        {
            $item->tag(self::CACHE_CUSTOMERS)->expiresAfter(60);
            return $this->customerRepo->getCountOfUser($usrId);
        });

        $getCustomersCacheId = "getAllCustomers-$usrId-$page-$limit";
        $jsonCustomersList = $cachePool->get($getCustomersCacheId, function (ItemInterface $item) use ($usrId, $serializer, $page, $limit, $customerCount, $paginator)
        {
            $item->tag(self::CACHE_CUSTOMERS)->expiresAfter(60);
            $list = $this->customerRepo->findOfUserWithPagination($usrId, $page, $limit);

            $pages = $paginator->asArray("api_customers", $page, $limit, $customerCount);
            return $serializer->serialize(array("_pages" => $pages, "items" => $list), 'json', $this->serializationContext);
        });

        return new JsonResponse(data: $jsonCustomersList, status: Response::HTTP_OK, json: true);
    }

    /**
     * Create a new customer for your company.
     */
    #[OA\RequestBody(description: "Create a new customer object", required: true, content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["createCustomers"])))]
    #[OA\Response(response: 201, description: "Your customer has been created", content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["getCustomers"])))]
    #[OA\Tag(name: "Customers")]
    #[Route('/api/customers/', name: 'api_customer_create', methods: ['POST'], format: 'json')]
    public function createCustomer(
        Request                $request,
        SerializerInterface    $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface  $urlGenerator,
        ValidatorInterface     $validator
    ) : JsonResponse
    {
        $customer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        $errors = $validator->validate($customer);
        if($errors->count() > 0) {
            throw new BadRequestHttpException("Unable to validate customer creation data");
        }

        $customer->setUser($this->getUser());
        $customer->setCreationDate(new \DateTimeImmutable());

        $em->persist($customer);
        $em->flush();

        $jsonCustomer = $serializer->serialize($customer, 'json', $this->serializationContext);
        $location = $urlGenerator->generate('api_customer_get', ['id' => $customer->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Fetch one of your customer.
     */
    #[OA\PathParameter(name: "id", description: "The id of your customer", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "The customer data is in the response body", content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["getCustomers"])))]
    #[OA\Tag(name: "Customers")]
    #[Route('/api/customers/{id}', name: 'api_customer_get', methods: ['GET'], format: 'json')]
    public function fetchCustomer(Customer $customer, SerializerInterface $serializer): JsonResponse
    {
        $this->checkUserRightsOnCustomer($customer);

        $jsonCustomer = $serializer->serialize($customer, 'json', $this->serializationContext);
        return new JsonResponse(data: $jsonCustomer, status: Response::HTTP_OK, json: true);
    }

    /**
     * Update one of your customer.
     */
    #[OA\RequestBody(description: "Update an existing customer", required: true, content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["createCustomers"])))]
    #[OA\PathParameter(name: "id", description: "The id of the customer to update", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 201, description: "Your customer has been created", content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["getCustomers"])))]
    #[OA\Tag(name: "Customers")]
    #[Route('/api/customers/{id}', name: 'api_customer_update', methods: ['PUT'], format: 'json')]
    public function updateCustomer(
        Request                 $request,
        SerializerInterface     $serializer,
        Customer                $customer,
        EntityManagerInterface  $em,
        TagAwareCacheInterface  $cachePool,
        ValidatorInterface      $validator
    ) : JsonResponse
    {
        $this->checkUserRightsOnCustomer($customer);

        $updatedCustomer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        $errors = $validator->validate($updatedCustomer);
        if($errors->count() > 0) {
            throw new ApiValidationException($errors);
        }

        $customer->setEmail($updatedCustomer->getEmail());
        $customer->setFirstName($updatedCustomer->getFirstName());
        $customer->setLastName($updatedCustomer->getLastName());

        $em->persist($customer);
        $em->flush();

        // We modified customers collection, invalidate cache
        $cachePool->invalidateTags([self::CACHE_CUSTOMERS]);

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete one of your customer.
     */
    #[OA\PathParameter(name: "id", description: "The id of your customer", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 204, description: "Your customer has been deleted", content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ["getCustomers"])))]
    #[OA\Tag(name: "Customers")]
    #[Route('/api/customers/{id}', name: 'api_customer_delete', methods: ['DELETE'], format: 'json')]
    public function deleteCustomer(Customer $customer, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $this->checkUserRightsOnCustomer($customer);

        $em->remove($customer);
        $em->flush();

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