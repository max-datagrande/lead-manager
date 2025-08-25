import React, { useState, useEffect } from 'react';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import JsonViewer from '@/components/ui/json-viewer';
import { AlertCircle, CheckCircle, Clock, Globe } from 'lucide-react';
import { formatDateTime } from '@/utils/table';

const PostbackApiRequestsViewer = ({ postbackId }) => {
  const [apiRequests, setApiRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!postbackId) return;

    const fetchApiRequests = async () => {
      try {
        setLoading(true);
        const response = await fetch(`/postbacks/${postbackId}/api-requests`);
        const data = await response.json();

        if (data.success) {
          setApiRequests(data.data);
        } else {
          setError('Error loading API requests');
        }
      } catch (err) {
        setError('Connection error');
        console.error('Error fetching API requests:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchApiRequests();
  }, [postbackId]);

  const getStatusBadge = (statusCode, errorMessage) => {
    if (statusCode >= 200 && statusCode < 300) {
      return (
        <Badge variant="default" className="bg-green-100 text-green-800 border-green-200 hover:bg-green-200">
          <CheckCircle className="w-3 h-3 mr-1" />
          Success ({statusCode})
        </Badge>
      );
    } else if (statusCode >= 400 && statusCode < 500) {
      return (
        <Badge variant="destructive" className="border-orange-200 bg-orange-100 text-orange-800 hover:bg-orange-200">
          <AlertCircle className="mr-1 h-3 w-3" />
          Client Error ({statusCode})
        </Badge>
      );
    } else if (statusCode >= 500) {
      return (
        <Badge variant="destructive" className="border-red-200 bg-red-100 text-red-800 hover:bg-red-200">
          <AlertCircle className="mr-1 h-3 w-3" />
          Server Error ({statusCode})
        </Badge>
      );
    } else if (errorMessage) {
      return (
        <Badge variant="destructive" className="border-red-200 bg-red-100 text-red-800">
          <AlertCircle className="mr-1 h-3 w-3" />
          Network Error
        </Badge>
      );
    } else {
      return (
        <Badge variant="secondary" className="bg-yellow-100 text-yellow-800 border-yellow-200">
          <Clock className="w-3 h-3 mr-1" />
          {statusCode || 'Pendiente'}
        </Badge>
      );
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="flex items-center gap-2">
          <Clock className="h-4 w-4 animate-spin" />
          <span>Loading API requests...</span>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="flex items-center gap-2 text-red-500">
          <AlertCircle className="h-4 w-4" />
          <span>{error}</span>
        </div>
      </div>
    );
  }

  if (apiRequests.length === 0) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="text-center">
          <Globe className="mx-auto mb-2 h-8 w-8 text-gray-400" />
          <p className="text-gray-500">No API requests were found for this postback</p>
        </div>
      </div>
    );
  }

  return (
    <div className="w-full">
      <Tabs defaultValue={apiRequests[0]?.id?.toString()} className="w-full">
        <TabsList className="grid w-full" style={{ gridTemplateColumns: `repeat(${Math.min(apiRequests.length, 4)}, 1fr)` }}>
          {apiRequests.slice(0, 4).map((request, index) => (
            <TabsTrigger key={request.id} value={request.id.toString()} className="flex items-center gap-2 relative">
              <span className={`w-2 h-2 rounded-full ${
                request.status_code >= 200 && request.status_code < 300
                  ? 'bg-green-500'
                  : request.status_code >= 400
                  ? 'bg-red-500'
                  : 'bg-yellow-500'
              }`} />
              <span>Intento {index + 1}</span>
              {request.error_message && (
                <AlertCircle className="w-3 h-3 text-red-500" />
              )}
              <span className="text-xs text-gray-500 ml-1">
                ({request.status_code || 'N/A'})
              </span>
            </TabsTrigger>
          ))}
          {apiRequests.length > 4 && (
            <TabsTrigger value="more" className="text-xs">
              +{apiRequests.length - 4} more
            </TabsTrigger>
          )}
        </TabsList>

        {apiRequests.slice(0, 4).map((request) => (
          <TabsContent key={request.id} value={request.id.toString()}>
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-3">
                    <div className={`w-3 h-3 rounded-full ${
                      request.status_code >= 200 && request.status_code < 300
                        ? 'bg-green-500'
                        : request.status_code >= 400
                        ? 'bg-red-500'
                        : 'bg-yellow-500'
                    }`} />
                    <div>
                      <div className="font-medium flex items-center gap-2">
                        <span className="text-blue-600 font-mono text-sm">{request.method}</span>
                        <span>{request.endpoint}</span>
                      </div>
                      <div className="text-sm text-gray-500 flex items-center gap-2">
                        <span>Service: {request.service}</span>
                        <span>•</span>
                        <span>{formatDateTime(request.created_at)}</span>
                        <span>•</span>
                        <span className={`font-mono ${
                          request.response_time_ms > 5000
                            ? 'text-red-600'
                            : request.response_time_ms > 2000
                            ? 'text-orange-600'
                            : 'text-green-600'
                        }`}>
                          {request.response_time_ms || 0}ms
                        </span>
                        {request.error_message && (
                          <>
                            <span>•</span>
                            <span className="text-red-600 text-xs truncate max-w-32" title={request.error_message}>
                              {request.error_message}
                            </span>
                          </>
                        )}
                      </div>
                    </div>
                  </div>
                  {getStatusBadge(request.status_code, request.error_message)}
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                {request.error_message && (
                  <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                    <div className="flex items-center gap-2 text-red-700 font-medium mb-1">
                      <AlertCircle className="h-4 w-4" />
                      Error
                    </div>
                    <p className="text-red-600 text-sm">{request.error_message}</p>
                  </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <JsonViewer
                    data={request.request_data}
                    title={(
                      <div className="flex items-center gap-2">
                        <span>Request Data</span>
                        <Badge variant="outline" className="text-xs">Sent</Badge>
                      </div>
                    )}
                  />

                  <JsonViewer
                    data={request.response_data}
                    title={(
                      <div className="flex items-center gap-2">
                        <span>Response Data</span>
                        <Badge variant="outline" className="text-xs">Received</Badge>
                      </div>
                    )}
                  />
                </div>

                <Separator />

                <div className="flex items-center gap-4 text-sm text-gray-600">
                  <span>Request ID: {request.request_id}</span>
                  <span>•</span>
                  <span>ID: {request.id}</span>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        ))}

        {apiRequests.length > 4 && (
          <TabsContent value="more">
            <Card>
              <CardHeader>
                <CardTitle>More requests</CardTitle>
                <CardDescription>
                  Showing {apiRequests.length - 4} additional requests
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  {apiRequests.slice(4).map((request, index) => (
                    <div key={request.id} className="flex items-center justify-between p-2 border rounded">
                      <div className="flex items-center gap-2">
                        <span className="font-medium">Request {index + 5}</span>
                        <span className="text-sm text-gray-500">{request.method} {request.endpoint}</span>
                      </div>
                      <div className="flex items-center gap-2">
                        {getStatusBadge(request.status_code, request.error_message)}
                        <span className="text-xs text-gray-500">{formatDateTime(request.created_at)}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        )}
      </Tabs>
    </div>
  );
};

export default PostbackApiRequestsViewer;
