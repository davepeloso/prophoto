import React, { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
    Settings as SettingsIcon,
    FileText,
    Tags,
    Plus,
    X,
    ArrowLeft
} from 'lucide-react';

interface SettingsData {
    schema: {
        path: string;
        filename: string;
        sequence_start: number;
        sequence_padding: number;
    };
    metadata: {
        chart_fields: string[];
        default_chart_field: string;
        tagging: {
            quick_tags: string[];
        };
    };
}

interface SettingsProps {
    settings: SettingsData;
    availableChartKeys: Record<string, string>;
}

export default function Settings({ settings, availableChartKeys }: SettingsProps) {
    const { data, setData, patch, processing, errors } = useForm(settings);
    const [activeTab, setActiveTab] = useState('schema');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch('/ingest/settings', {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Settings saved successfully');
            },
        });
    };

    const updateNestedValue = (path: string, value: any) => {
        const keys = path.split('.');
        const newData = { ...data };
        let current: any = newData;

        for (let i = 0; i < keys.length - 1; i++) {
            current = current[keys[i]];
        }

        current[keys[keys.length - 1]] = value;
        setData(newData as any);
    };

    const addChartField = (field: string) => {
        if (field && !data.metadata.chart_fields.includes(field)) {
            const newFields = [...data.metadata.chart_fields, field];
            updateNestedValue('metadata.chart_fields', newFields);
        }
    };

    const removeChartField = (index: number) => {
        const newFields = data.metadata.chart_fields.filter((_, i) => i !== index);
        updateNestedValue('metadata.chart_fields', newFields);
    };

    const addQuickTag = () => {
        const newTags = [...data.metadata.tagging.quick_tags, ''];
        updateNestedValue('metadata.tagging.quick_tags', newTags);
    };

    const updateQuickTag = (index: number, value: string) => {
        const newTags = [...data.metadata.tagging.quick_tags];
        newTags[index] = value;
        updateNestedValue('metadata.tagging.quick_tags', newTags);
    };

    const removeQuickTag = (index: number) => {
        const newTags = data.metadata.tagging.quick_tags.filter((_, i) => i !== index);
        updateNestedValue('metadata.tagging.quick_tags', newTags);
    };

    // Get chart keys not already selected
    const availableKeysForSelection = Object.entries(availableChartKeys).filter(
        ([key]) => !data.metadata.chart_fields.includes(key)
    );

    return (
        <>
            <Head title="Settings - Ingest" />

            <div className="min-h-screen bg-background">
                <div className="border-b">
                    <div className="container mx-auto px-6 py-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => router.visit('/ingest')}
                                >
                                    <ArrowLeft className="h-4 w-4 mr-2" />
                                    Back to Ingest
                                </Button>
                                <div className="flex items-center gap-2">
                                    <SettingsIcon className="h-6 w-6" />
                                    <h1 className="text-2xl font-semibold">Ingest Settings</h1>
                                </div>
                            </div>
                            <Button onClick={handleSubmit} disabled={processing}>
                                {processing ? 'Saving...' : 'Save All Changes'}
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="container mx-auto px-6 py-8">
                    <form onSubmit={handleSubmit}>
                        <Tabs value={activeTab} onValueChange={setActiveTab}>
                            <TabsList className="mb-6">
                                <TabsTrigger value="schema">
                                    <FileText className="h-4 w-4 mr-2" />
                                    File Schema
                                </TabsTrigger>
                                <TabsTrigger value="metadata">
                                    <Tags className="h-4 w-4 mr-2" />
                                    Metadata & Tags
                                </TabsTrigger>
                            </TabsList>

                            {/* Tab 1: File Schema */}
                            <TabsContent value="schema">
                                <div className="space-y-6">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Directory & Filename Patterns</CardTitle>
                                            <CardDescription>
                                                Define how files are organized and named after ingestion
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div>
                                                <Label htmlFor="schema_path">Directory Path Pattern</Label>
                                                <Input
                                                    id="schema_path"
                                                    value={data.schema.path}
                                                    onChange={(e) => updateNestedValue('schema.path', e.target.value)}
                                                    placeholder="shoots/{date:Y}/{date:m}/{camera}"
                                                />
                                                {errors['schema.path'] && (
                                                    <p className="text-sm text-destructive mt-1">{errors['schema.path']}</p>
                                                )}
                                                <p className="text-sm text-muted-foreground mt-1">
                                                    Example output: <code className="bg-muted px-2 py-1 rounded">
                                                        shoots/2025/01/Canon-EOS-R5
                                                    </code>
                                                </p>
                                            </div>

                                            <div>
                                                <Label htmlFor="schema_filename">Filename Pattern</Label>
                                                <Input
                                                    id="schema_filename"
                                                    value={data.schema.filename}
                                                    onChange={(e) => updateNestedValue('schema.filename', e.target.value)}
                                                    placeholder="{sequence}-{original}"
                                                />
                                                {errors['schema.filename'] && (
                                                    <p className="text-sm text-destructive mt-1">{errors['schema.filename']}</p>
                                                )}
                                                <p className="text-sm text-muted-foreground mt-1">
                                                    Example output: <code className="bg-muted px-2 py-1 rounded">
                                                        001-IMG_1234.jpg
                                                    </code>
                                                </p>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="sequence_start">Sequence Start</Label>
                                                    <Input
                                                        id="sequence_start"
                                                        type="number"
                                                        value={data.schema.sequence_start}
                                                        onChange={(e) =>
                                                            updateNestedValue('schema.sequence_start', parseInt(e.target.value) || 0)
                                                        }
                                                    />
                                                    {errors['schema.sequence_start'] && (
                                                        <p className="text-sm text-destructive mt-1">{errors['schema.sequence_start']}</p>
                                                    )}
                                                </div>
                                                <div>
                                                    <Label htmlFor="sequence_padding">Sequence Padding</Label>
                                                    <Input
                                                        id="sequence_padding"
                                                        type="number"
                                                        value={data.schema.sequence_padding}
                                                        onChange={(e) =>
                                                            updateNestedValue('schema.sequence_padding', parseInt(e.target.value) || 1)
                                                        }
                                                    />
                                                    {errors['schema.sequence_padding'] && (
                                                        <p className="text-sm text-destructive mt-1">{errors['schema.sequence_padding']}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Available Placeholders</CardTitle>
                                            <CardDescription>
                                                Use these in your path and filename patterns
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="space-y-2">
                                                    <h4 className="font-medium text-sm">Date Placeholders</h4>
                                                    <div className="space-y-1 text-sm">
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{date:Y}'}</code> - Year (2025)</div>
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{date:m}'}</code> - Month (01-12)</div>
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{date:d}'}</code> - Day (01-31)</div>
                                                    </div>
                                                </div>
                                                <div className="space-y-2">
                                                    <h4 className="font-medium text-sm">Camera Placeholders</h4>
                                                    <div className="space-y-1 text-sm">
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{camera}'}</code> - Camera model</div>
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{model}'}</code> - Camera model</div>
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{lens}'}</code> - Lens name</div>
                                                    </div>
                                                </div>
                                                <div className="space-y-2">
                                                    <h4 className="font-medium text-sm">File Placeholders</h4>
                                                    <div className="space-y-1 text-sm">
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{sequence}'}</code> - Sequential number</div>
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{original}'}</code> - Original filename</div>
                                                        <div><code className="bg-muted px-2 py-1 rounded">{'{uuid}'}</code> - Unique identifier</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </TabsContent>

                            {/* Tab 2: Metadata & Tags */}
                            <TabsContent value="metadata">
                                <div className="space-y-6">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Chart Fields</CardTitle>
                                            <CardDescription>
                                                Select metadata fields to display in charts and analytics
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div>
                                                <Label>Selected Chart Fields</Label>
                                                <div className="flex flex-wrap gap-2 mt-2">
                                                    {data.metadata.chart_fields.map((field, index) => (
                                                        <div
                                                            key={index}
                                                            className="flex items-center gap-1 bg-muted px-3 py-1 rounded-full text-sm"
                                                        >
                                                            <span>{availableChartKeys[field] || field}</span>
                                                            <button
                                                                type="button"
                                                                onClick={() => removeChartField(index)}
                                                                className="ml-1 hover:text-destructive"
                                                            >
                                                                <X className="h-3 w-3" />
                                                            </button>
                                                        </div>
                                                    ))}
                                                    {data.metadata.chart_fields.length === 0 && (
                                                        <span className="text-sm text-muted-foreground">
                                                            No chart fields selected
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            {availableKeysForSelection.length > 0 && (
                                                <div>
                                                    <Label>Add Chart Field</Label>
                                                    <Select onValueChange={addChartField}>
                                                        <SelectTrigger className="w-full mt-2">
                                                            <SelectValue placeholder="Select a metadata field to add..." />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {availableKeysForSelection.map(([key, label]) => (
                                                                <SelectItem key={key} value={key}>
                                                                    {label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                            <div>
                                                <Label htmlFor="default_chart_field">Default Chart Field</Label>
                                                <Select
                                                    value={data.metadata.default_chart_field}
                                                    onValueChange={(value) => updateNestedValue('metadata.default_chart_field', value)}
                                                >
                                                    <SelectTrigger className="w-full mt-2">
                                                        <SelectValue placeholder="Select default chart field..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {Object.entries(availableChartKeys).map(([key, label]) => (
                                                            <SelectItem key={key} value={key}>
                                                                {label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <p className="text-sm text-muted-foreground mt-1">
                                                    The field shown by default in single-field chart displays
                                                </p>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Quick Tags</CardTitle>
                                            <CardDescription>
                                                Predefined tags for quick access during image tagging
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="space-y-2">
                                                {data.metadata.tagging.quick_tags.map((tag, index) => (
                                                    <div key={index} className="flex items-center gap-2">
                                                        <Input
                                                            value={tag}
                                                            onChange={(e) => updateQuickTag(index, e.target.value)}
                                                            placeholder="Enter tag name"
                                                        />
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => removeQuickTag(index)}
                                                        >
                                                            <X className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                ))}
                                                {data.metadata.tagging.quick_tags.length === 0 && (
                                                    <p className="text-sm text-muted-foreground">
                                                        No quick tags configured. Add some to speed up tagging.
                                                    </p>
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={addQuickTag}
                                                >
                                                    <Plus className="h-4 w-4 mr-2" />
                                                    Add Tag
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </TabsContent>
                        </Tabs>

                        <div className="flex justify-end gap-4 mt-8 pt-6 border-t">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.visit('/ingest')}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving...' : 'Save All Changes'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
