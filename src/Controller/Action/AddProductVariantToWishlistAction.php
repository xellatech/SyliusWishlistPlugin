<?php

/*
 * This file has been created by developers from BitBag.
 * Feel free to contact us once you face any issues or want to start
 * another great project.
 * You can find more information about us on https://bitbag.shop and write us
 * an email on mikolaj.krol@bitbag.pl.
 */

declare(strict_types=1);

namespace BitBag\SyliusWishlistPlugin\Controller\Action;

use BitBag\SyliusWishlistPlugin\Context\WishlistContextInterface;
use BitBag\SyliusWishlistPlugin\Entity\WishlistInterface;
use BitBag\SyliusWishlistPlugin\Entity\WishlistProductInterface;
use BitBag\SyliusWishlistPlugin\Factory\WishlistProductFactoryInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddProductVariantToWishlistAction
{
    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var ProductVariantRepositoryInterface */
    private $productVariantRepository;

    /** @var WishlistContextInterface */
    private $wishlistContext;

    /** @var WishlistProductFactoryInterface */
    private $wishlistProductFactory;

    /** @var ObjectManager */
    private $wishlistManager;

    /** @var FlashBagInterface */
    private $flashBag;

    /** @var TranslatorInterface */
    private $translator;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var string */
    private $wishlistCookieToken;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        ProductVariantRepositoryInterface $productVariantRepository,
        WishlistContextInterface $wishlistContext,
        WishlistProductFactoryInterface $wishlistProductFactory,
        ObjectManager $wishlistManager,
        FlashBagInterface $flashBag,
        TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator,
        string $wishlistCookieToken
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->productVariantRepository = $productVariantRepository;
        $this->wishlistContext = $wishlistContext;
        $this->wishlistProductFactory = $wishlistProductFactory;
        $this->wishlistManager = $wishlistManager;
        $this->urlGenerator = $urlGenerator;
        $this->wishlistCookieToken = $wishlistCookieToken;
        $this->flashBag = $flashBag;
        $this->translator = $translator;
    }

    public function __invoke(Request $request): Response
    {
        /** @var ProductVariantInterface|null $variant */
        $variant = $this->productVariantRepository->find($request->get('variantId'));

        if (null === $variant) {
            throw new NotFoundHttpException();
        }

        $wishlist = $this->wishlistContext->getWishlist($request);

        /** @var WishlistProductInterface $wishlistProduct */
        $wishlistProduct = $this->wishlistProductFactory->createForWishlistAndVariant($wishlist, $variant);

        $wishlist->addWishlistProduct($wishlistProduct);

        if (null === $wishlist->getId()) {
            $this->wishlistManager->persist($wishlist);
        }

        $this->wishlistManager->flush();

        $this->flashBag->add('success', $this->translator->trans('bitbag_sylius_wishlist_plugin.ui.added_wishlist_item'));

        $response = new RedirectResponse($this->urlGenerator->generate('bitbag_sylius_wishlist_plugin_shop_wishlist_list_products'));

        $token = $this->tokenStorage->getToken();

        if (null === $token || !is_object($token->getUser())) {
            $this->addWishlistToResponseCookie($wishlist, $response);
        }

        return $response;
    }

    private function addWishlistToResponseCookie(WishlistInterface $wishlist, Response $response): void
    {
        $cookie = new Cookie($this->wishlistCookieToken, $wishlist->getToken(), strtotime('+1 year'));

        $response->headers->setCookie($cookie);
    }
}
