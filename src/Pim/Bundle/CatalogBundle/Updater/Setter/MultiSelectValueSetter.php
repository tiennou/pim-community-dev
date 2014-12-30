<?php

namespace Pim\Bundle\CatalogBundle\Updater\Setter;

use Pim\Bundle\CatalogBundle\Builder\ProductBuilderInterface;
use Pim\Bundle\CatalogBundle\Entity\AttributeOption;
use Pim\Bundle\CatalogBundle\Entity\Repository\AttributeOptionRepository;
use Pim\Bundle\CatalogBundle\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Builder\ProductBuilder;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Updater\InvalidArgumentException;
use Pim\Bundle\CatalogBundle\Validator\AttributeValidatorHelper;

/**
 * Sets a multi select value in many products
 *
 * @author    Olivier Soulet <olivier.soulet@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MultiSelectValueSetter extends AbstractValueSetter
{
    /** @var AttributeOptionRepository */
    protected $attrOptionRepository;

    /**
     * @param ProductBuilderInterface   $productBuilder
     * @param AttributeValidatorHelper  $attrValidatorHelper
     * @param AttributeOptionRepository $attrOptionRepository
     * @param array                     $supportedTypes
     */
    public function __construct(
        ProductBuilderInterface $productBuilder,
        AttributeValidatorHelper $attrValidatorHelper,
        AttributeOptionRepository $attrOptionRepository,
        array $supportedTypes
    ) {
        parent::__construct($productBuilder, $attrValidatorHelper);
        $this->attrOptionRepository = $attrOptionRepository;
        $this->supportedTypes       = $supportedTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(array $products, AttributeInterface $attribute, $data, $locale = null, $scope = null)
    {
        $this->checkLocaleAndScope($attribute, $locale, $scope, 'multi select');

        if (!is_array($data)) {
            throw InvalidArgumentException::arrayExpected(
                $attribute->getCode(),
                'setter',
                'multi select',
                gettype($data)
            );
        }

        $attributeOptions = [];
        foreach ($data as $attributeOption) {
            $this->checkData($attribute, $attributeOption);

            $option = $this->attrOptionRepository->findOneBy(
                ['code' => $attributeOption['code'], 'attribute' => $attribute]
            );
            if (null === $option) {
                throw InvalidArgumentException::arrayInvalidKey(
                    $attribute->getCode(),
                    'code',
                    sprintf('Option with code "%s" does not exist', $attributeOption['code']),
                    'setter',
                    'multi select',
                    gettype($data)
                );
            }

            $attributeOptions[] = $option;
        }

        foreach ($products as $product) {
            $this->setOptions($attribute, $product, $attributeOptions, $locale, $scope);
        }
    }

    /**
     * Check if data are valid
     *
     * @param AttributeInterface $attribute
     * @param mixed              $attributeOption
     */
    protected function checkData(AttributeInterface $attribute, $attributeOption)
    {
        if (!is_array($attributeOption)) {
            throw InvalidArgumentException::arrayOfArraysExpected(
                $attribute->getCode(),
                'setter',
                'multi select',
                gettype($attributeOption)
            );
        }

        if (!array_key_exists('attribute', $attributeOption)) {
            throw InvalidArgumentException::arrayKeyExpected(
                $attribute->getCode(),
                'attribute',
                'setter',
                'multi select',
                gettype($attributeOption)
            );
        }

        if (!array_key_exists('code', $attributeOption)) {
            throw InvalidArgumentException::arrayKeyExpected(
                $attribute->getCode(),
                'code',
                'setter',
                'multi select',
                gettype($attributeOption)
            );
        }
    }

    /**
     * Set options into the product value
     *
     * @param AttributeInterface $attribute
     * @param ProductInterface   $product
     * @param array              $attributeOptions
     * @param string             $locale
     * @param string             $scope
     */
    protected function setOptions(
        AttributeInterface $attribute,
        ProductInterface $product,
        $attributeOptions,
        $locale,
        $scope
    ) {
        $value = $product->getValue($attribute->getCode(), $locale, $scope);
        if (null === $value) {
            $value = $this->productBuilder->addProductValue($product, $attribute, $locale, $scope);
        }

        foreach ($value->getOptions() as $option) {
            $value->removeOption($option);
        }

        foreach ($attributeOptions as $attributeOption) {
            $value->addOption($attributeOption);
        }
    }
}