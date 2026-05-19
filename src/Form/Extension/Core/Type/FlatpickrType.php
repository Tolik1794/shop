<?php

namespace App\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToDateTimeTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlatpickrType extends AbstractType
{

	public const DEFAULT_FORMAT = \IntlDateFormatter::MEDIUM;
	public const HTML5_FORMAT = 'yyyy-MM-dd';

	private const ACCEPTED_FORMATS = [
		\IntlDateFormatter::FULL,
		\IntlDateFormatter::LONG,
		\IntlDateFormatter::MEDIUM,
		\IntlDateFormatter::SHORT,
	];

	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$dateFormat = \is_int($options['format']) ? $options['format'] : self::DEFAULT_FORMAT;
		$timeFormat = \IntlDateFormatter::NONE;
		$calendar = \IntlDateFormatter::GREGORIAN;
		$pattern = \is_string($options['format']) ? $options['format'] : '';

		if (!\in_array($dateFormat, self::ACCEPTED_FORMATS, true)) {
			throw new InvalidOptionsException('The "format" option must be one of the IntlDateFormatter constants (FULL, LONG, MEDIUM, SHORT) or a string representing a custom format.');
		}

		if ('' !== $pattern && !str_contains($pattern, 'y') && !str_contains($pattern, 'M') && !str_contains($pattern, 'd')) {
			throw new InvalidOptionsException(sprintf('The "format" option should contain the letters "y", "M" or "d". Its current value is "%s".', $pattern));
		}

		$builder->addViewTransformer(new DateTimeToLocalizedStringTransformer(
			$options['model_timezone'],
			$options['view_timezone'],
			$dateFormat,
			$timeFormat,
			$calendar,
			$pattern
		));

		if ('datetime_immutable' === $options['input']) {
			$builder->addModelTransformer(new DateTimeImmutableToDateTimeTransformer());
		} elseif ('string' === $options['input']) {
			$builder->addModelTransformer(new ReversedTransformer(
				new DateTimeToStringTransformer($options['model_timezone'], $options['model_timezone'], $options['input_format'])
			));
		} elseif ('timestamp' === $options['input']) {
			$builder->addModelTransformer(new ReversedTransformer(
				new DateTimeToTimestampTransformer($options['model_timezone'], $options['model_timezone'])
			));
		} elseif ('array' === $options['input']) {
			$builder->addModelTransformer(new ReversedTransformer(
				new DateTimeToArrayTransformer($options['model_timezone'], $options['model_timezone'], ['year', 'month', 'day'])
			));
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function finishView(FormView $view, FormInterface $form, array $options)
	{
		$view->vars['type'] = 'date';

		if ($options['alt_input']) $view->vars['attr']['data-alt-input'] = 'true';
		if ($options['alt_format']) $view->vars['attr']['data-alt-format'] = $options['alt_format'];
		if ($options['min_date']) $view->vars['attr']['data-min-date'] = $options['min_date'];
		if ($options['date_format']) $view->vars['attr']['data-date-format'] = $options['date_format'];
		/** @var \DateTimeInterface $max_date */
		if ($max_date = $options['max_date']) $view->vars['attr']['data-max-date'] = $max_date->format($options['date_format']);

		if ($form->getConfig()->hasAttribute('formatter')) {
			$pattern = $form->getConfig()->getAttribute('formatter')->getPattern();

			// remove special characters unless the format was explicitly specified
			if (!\is_string($options['format'])) {
				// remove quoted strings first
				$pattern = preg_replace('/\'[^\']+\'/', '', $pattern);

				// remove remaining special chars
				$pattern = preg_replace('/[^yMd]+/', '', $pattern);
			}

			// set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
			// lookup various formats at http://userguide.icu-project.org/formatparse/datetime
			if (preg_match('/^([yMd]+)[^yMd]*([yMd]+)[^yMd]*([yMd]+)$/', $pattern)) {
				$pattern = preg_replace(['/y+/', '/M+/', '/d+/'], ['{{ year }}', '{{ month }}', '{{ day }}'], $pattern);
			} else {
				// default fallback
				$pattern = '{{ year }}{{ month }}{{ day }}';
			}

			$view->vars['date_pattern'] = $pattern;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver)
	{
		$placeholderDefault = function (Options $options) {
			return $options['required'] ? null : '';
		};

		$placeholderNormalizer = function (Options $options, $placeholder) use ($placeholderDefault) {
			if (\is_array($placeholder)) {
				$default = $placeholderDefault($options);

				return array_merge(
					['year' => $default, 'month' => $default, 'day' => $default],
					$placeholder
				);
			}

			return [
				'year' => $placeholder,
				'month' => $placeholder,
				'day' => $placeholder,
			];
		};

		$choiceTranslationDomainNormalizer = function (Options $options, $choiceTranslationDomain) {
			if (\is_array($choiceTranslationDomain)) {
				$default = false;

				return array_replace(
					['year' => $default, 'month' => $default, 'day' => $default],
					$choiceTranslationDomain
				);
			}

			return [
				'year' => $choiceTranslationDomain,
				'month' => $choiceTranslationDomain,
				'day' => $choiceTranslationDomain,
			];
		};

		$resolver->setDefaults([
			'years' => range((int)date('Y') - 5, (int)date('Y') + 5),
			'months' => range(1, 12),
			'days' => range(1, 31),
			'widget' => 'choice',
			'input' => 'datetime',
			'format' => self::HTML5_FORMAT,
			'model_timezone' => null,
			'view_timezone' => null,
			'placeholder' => $placeholderDefault,
			'html5' => true,
			// Don't modify \DateTime classes by reference, we treat
			// them like immutable value objects
			'by_reference' => false,
			'error_bubbling' => false,
			// If initialized with a \DateTime object, FormType initializes
			// this option to "\DateTime". Since the internal, normalized
			// representation is not \DateTime, but an array, we need to unset
			// this option.
			'data_class' => null,
			'compound' => false,
			'empty_data' => function (Options $options) {
				return $options['compound'] ? [] : '';
			},
			'choice_translation_domain' => false,
			'input_format' => 'Y-m-d',
			'invalid_message' => 'Please enter a valid date.',
			'date_format' => 'Y-m-d',
			'max_date' => false,
			'min_date' => false,
			'alt_input' => false,
			'alt_format' => '',
		]);


		$resolver->setNormalizer('placeholder', $placeholderNormalizer);
		$resolver->setNormalizer('choice_translation_domain', $choiceTranslationDomainNormalizer);

		$resolver->setAllowedValues('input', [
			'datetime',
			'datetime_immutable',
			'string',
			'timestamp',
			'array',
		]);

		$resolver->setAllowedTypes('format', ['int', 'string']);
		$resolver->setAllowedTypes('max_date', [\DateTimeInterface::class, 'bool']);
		$resolver->setAllowedTypes('min_date', [\DateTimeInterface::class, 'bool']);
		$resolver->setAllowedTypes('alt_input', 'bool');
		$resolver->setAllowedTypes('alt_format', 'string');
		$resolver->setAllowedTypes('years', 'array');
		$resolver->setAllowedTypes('months', 'array');
		$resolver->setAllowedTypes('days', 'array');
		$resolver->setAllowedTypes('input_format', 'string');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getBlockPrefix(): string
	{
		return 'flatpickr_date';
	}
}