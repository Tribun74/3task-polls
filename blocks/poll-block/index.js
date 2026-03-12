/**
 * TPoll Gutenberg Block
 *
 * @package TPoll
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, Placeholder, Spinner } = wp.components;
    const { __ } = wp.i18n;
    const { useState, useEffect } = wp.element;
    const ServerSideRender = wp.serverSideRender;

    registerBlockType('tpoll/poll', {
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { pollId } = attributes;
            const blockProps = useBlockProps();

            // Get polls from localized data
            const polls = window.tpollBlockData ? window.tpollBlockData.polls : [];

            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    InspectorControls,
                    null,
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Poll Settings', '3task-polls'), initialOpen: true },
                        wp.element.createElement(
                            SelectControl,
                            {
                                label: __('Select Poll', '3task-polls'),
                                value: pollId,
                                options: polls,
                                onChange: function(value) {
                                    setAttributes({ pollId: parseInt(value, 10) });
                                }
                            }
                        )
                    )
                ),
                pollId ? (
                    wp.element.createElement(
                        ServerSideRender,
                        {
                            block: 'tpoll/poll',
                            attributes: attributes
                        }
                    )
                ) : (
                    wp.element.createElement(
                        Placeholder,
                        {
                            icon: 'chart-bar',
                            label: __('TPoll', '3task-polls'),
                            instructions: __('Select a poll from the sidebar settings.', '3task-polls')
                        },
                        wp.element.createElement(
                            SelectControl,
                            {
                                value: pollId,
                                options: polls,
                                onChange: function(value) {
                                    setAttributes({ pollId: parseInt(value, 10) });
                                }
                            }
                        )
                    )
                )
            );
        },

        save: function() {
            // Dynamic block - rendered on server
            return null;
        }
    });
})(window.wp);
