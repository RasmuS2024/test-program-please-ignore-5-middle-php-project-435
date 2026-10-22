import "@typespec/http";
import "@typespec/openapi";

using TypeSpec.Http;
using TypeSpec.OpenAPI;

@service(#{ title: "Online Store API" })
@info(#{ version: "1.0.0" })
namespace OnlineStoreApi;

alias SessionCookieAuth = ApiKeyAuth<ApiKeyLocation.cookie, "PHPSESSID">;

@doc("Problem type URIs understood by the store frontend.")
enum ProblemType {
  invalidCredentials: "https://hexlet.io/problems/invalid-credentials",
  emailTaken: "https://hexlet.io/problems/email-taken",
  validationError: "https://hexlet.io/problems/validation-error",
  addressRequired: "https://hexlet.io/problems/address-required",
  unavailableItems: "https://hexlet.io/problems/unavailable-items",
  unauthorized: "https://hexlet.io/problems/unauthorized",
  notFound: "https://hexlet.io/problems/not-found",
  internalError: "https://hexlet.io/problems/internal-error",
}

@doc("A monetary amount in whole rubles.")
model Money {
  @doc("Amount in whole rubles.")
  amount: int32;

  @doc("Always RUB.")
  currency: "RUB";
}

model Category {
  id: string;
  slug: string;
  name: string;
}

model Product {
  id: string;
  slug: string;
  name: string;
  description: string;
  price: Money;
  categoryId: string;

  @doc("Product image path. Omit it when no image is available.")
  imageUrl?: string;

  available: boolean;
}

model PageMeta {
  page: int32;
  perPage: int32;

  @doc("Number of products matching the current filters.")
  total: int32;

  totalPages: int32;
}

model ProductPage {
  items: Product[];
  meta: PageMeta;
}

model Promo {
  id: string;
  title: string;
  text: string;
  product: Product;
}

model User {
  id: string;
  email: string;
}

model Credentials {
  @doc("Email address. The server compares addresses case-insensitively and trims whitespace.")
  @format("email")
  email: string;

  @doc("Password, at least 6 characters.")
  @minLength(6)
  password: string;
}

model CartItem {
  productId: string;
  @minValue(1) quantity: int32;
}

model ShippingDetails {
  method: "delivery" | "pickup";
  recipientName: string;
  phone: string;

  @doc("Required when method is delivery.")
  address?: string;
}

model OrderItem {
  productId: string;

  @doc("Product name snapshot at purchase time.")
  productName: string;

  @doc("Unit price snapshot at purchase time.")
  priceAtPurchase: Money;

  quantity: int32;
}

model Order {
  id: string;

  @doc("Orders are created as paid and never change status.")
  status: "paid";

  items: OrderItem[];
  shipping: ShippingDetails;
  total: Money;
  createdAt: utcDateTime;
}

model CreateOrderRequest {
  @minItems(1) items: CartItem[];
  shipping: ShippingDetails;
}

@doc("RFC 9457 Problem Details. The frontend chooses the user-facing message from type.")
model ProblemDetails {
  `type`: ProblemType;
  title: string;
  status: int32;
  detail?: string;
}

@doc("Order validation failed because some products are missing or unavailable. type is unavailable-items.")
model UnavailableItemsProblem extends ProblemDetails {
  @doc("All missing or unavailable product IDs, not just the first one.")
  unavailableProductIds: string[];
}

model Ok<T> {
  @statusCode statusCode: 200;
  @body body: T;
}

model Created<T> {
  @statusCode statusCode: 201;
  @body body: T;
}

model NoContent {
  @statusCode statusCode: 204;
}

model ValidationErrorResponse {
  @statusCode statusCode: 400;
  @body body: ProblemDetails;
}

model OrderBadRequestResponse {
  @statusCode statusCode: 400;
  @body body: ProblemDetails | UnavailableItemsProblem;
}

model InvalidCredentialsResponse {
  @statusCode statusCode: 401;
  @body body: ProblemDetails;
}

model UnauthorizedResponse {
  @statusCode statusCode: 401;
  @body body: ProblemDetails;
}

model NotFoundResponse {
  @statusCode statusCode: 404;
  @body body: ProblemDetails;
}

model EmailTakenResponse {
  @statusCode statusCode: 409;
  @body body: ProblemDetails;
}

model InternalErrorResponse {
  @statusCode statusCode: 500;
  @body body: ProblemDetails;
}

model HealthResponse {
  status: "ok";
}

@route("/api/health")
interface Health {
  @get
  @doc("Liveness probe. Must not depend on PostgreSQL or the frontend.")
  check(): Ok<HealthResponse>;
}

@route("/api/users")
interface Users {
  @post
  @doc("Register a user and open an authenticated server-side session. The session cookie is HttpOnly.")
  create(@body body: Credentials): Created<User> | ValidationErrorResponse | EmailTakenResponse | InternalErrorResponse;
}

@route("/api/session")
interface Session {
  @post
  @doc("Log in and open a server-side session. The session cookie is HttpOnly.")
  login(@body body: Credentials): Ok<User> | ValidationErrorResponse | InvalidCredentialsResponse | InternalErrorResponse;

  @delete
  @useAuth(SessionCookieAuth)
  @doc("Destroy the current server-side session. The next GET /api/me returns 401.")
  logout(): NoContent | InternalErrorResponse;
}

@route("/api/me")
@useAuth(SessionCookieAuth)
interface Me {
  @get
  @doc("Return the currently authenticated user.")
  get(): Ok<User> | UnauthorizedResponse | InternalErrorResponse;
}

@route("/api/categories")
interface Categories {
  @get
  @doc("Return the category directory.")
  list(): Ok<Category[]> | InternalErrorResponse;
}

@route("/api/promos")
interface Promos {
  @get
  @doc("Return home page promo blocks with their products.")
  list(): Ok<Promo[]> | InternalErrorResponse;
}

@route("/api/products")
interface Products {
  @get
  @doc("Return a filtered and paginated product catalog.")
  list(
    @doc("Category slug. Omit or leave empty for all categories.")
    @query category?: string,
    @doc("Case-insensitive substring of the product name.")
    @query q?: string,
    @doc("Minimum unit price, inclusive.")
    @query priceMin?: int32,
    @doc("Maximum unit price, inclusive.")
    @query priceMax?: int32,
    @doc("true returns only available products; omit for all products.")
    @query available?: boolean,
    @doc("Page number, starting at 1.")
    @query @minValue(1) page?: int32 = 1,
    @doc("Page size. Defaults to 6; maximum 48.")
    @query @maxValue(48) perPage?: int32 = 6,
  ): Ok<ProductPage> | ValidationErrorResponse | InternalErrorResponse;

  @get
  @route("/{id}")
  @doc("Return one product by its catalog id.")
  get(@path id: string): Ok<Product> | NotFoundResponse | InternalErrorResponse;
}

@route("/api/orders")
@useAuth(SessionCookieAuth)
interface Orders {
  @post
  @doc("Create a paid order from the cart. Prices and totals are calculated by the server.")
  create(@body body: CreateOrderRequest): Created<Order> | UnauthorizedResponse | OrderBadRequestResponse | InternalErrorResponse;

  @get
  @doc("Return the current user's orders, newest first.")
  list(): Ok<Order[]> | UnauthorizedResponse | InternalErrorResponse;

  @get
  @route("/{id}")
  @doc("Return one of the current user's orders. Other users' orders are reported as not found.")
  get(@path id: string): Ok<Order> | UnauthorizedResponse | NotFoundResponse | InternalErrorResponse;
}
