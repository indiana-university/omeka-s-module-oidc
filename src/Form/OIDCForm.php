<?php

namespace OIDC\Form;

use Laminas\Filter\StringTrim;
use Laminas\Filter\StripTags;
use Laminas\Form\Element\Url;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Password;
use Omeka\Form\Element\RoleSelect;
use Omeka\Form\Element\SiteSelect;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\StringLength;
use Laminas\Validator\Callback;
use OIDC\Security\ProviderMetadataValidator;

class OIDCForm extends Form
{
    public function init() : void
    {
        // Discovery Document URI
        $this->add([
            'name'    => 'oidc_discovery',
            'type'    => Url::class,
            'options' => [
                'label' => 'OIDC Issuer URI',
                'info'  => 'Exact HTTPS issuer URI published by the OIDC provider.',
            ],
            'attributes' => [
                'id' => 'oidc_discovery',
                'required' => true,
            ]
        ]);

	/*
       //Default role for new users
        $this->add([
            'name'    => 'oidc_role',
            'type'    => RoleSelect::class,
            'options' => [
                'label' => 'New user role',
                'info'  => 'Role to automatically assign to new users.',
            ],
            'attributes' => [
                'id' => 'oidc_role',
                'required' => true,
            ]
        ]);

        //Default site for new users
        $this->add([
            'name'    => 'oidc_site',
            'type'    => SiteSelect::class,
            'options' => [
                'label' => 'New user site',
		        'info'  => 'Site to grant access for new users.',
            ],
            'attributes' => [
                'id' => 'oidc_site',
        		'required' => false,
            ]
	]);
	 */

	// …

        $inputFilter = new InputFilter();
        $inputFilter->add(
            $this->getInputFilterSpecification()['oidc_discovery'],
            'oidc_discovery'
        );
        $this->setInputFilter($inputFilter);
        $this->setPreferFormInputFilter(true);
    }

    public function getInputFilterSpecification(): array
    {
        return [
            'oidc_discovery' => [
                'required' => true,
                'filters' => [
                    ['name' => StringTrim::class],
                ],
                'validators' => [
                    [
                        'name' => Callback::class,
                        'options' => [
                            'callback' => static function ($value): bool {
                                if (! is_string($value)) {
                                    return false;
                                }

                                try {
                                    (new ProviderMetadataValidator())->validateIssuer($value);
                                    return true;
                                } catch (\UnexpectedValueException) {
                                    return false;
                                }
                            },
                            'messages' => [
                                Callback::INVALID_VALUE => 'Enter the exact HTTPS issuer URI without credentials, a query, or a fragment.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
