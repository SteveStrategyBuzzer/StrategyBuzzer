import { z, ZodSchema, ZodError } from "zod";
import type { Socket } from "socket.io";

export type ValidationResult<T> = 
  | { success: true; data: T }
  | { success: false; error: ZodError };

export function validateEvent<T>(schema: ZodSchema<T>, data: unknown): ValidationResult<T> {
  const result = schema.safeParse(data);
  if (result.success) {
    return { success: true, data: result.data };
  }
  return { success: false, error: result.error };
}

export function withValidation<T>(
  socket: Socket,
  schema: ZodSchema<T>,
  eventName: string,
  handler: (data: T) => void
): (data: unknown) => void {
  return (data: unknown) => {
    const result = validateEvent(schema, data);
    if (!result.success) {
      console.error(`[WS] Validation error for ${eventName}:`, result.error.issues);
      socket.emit("error", { 
        code: "VALIDATION_ERROR", 
        message: `Invalid ${eventName} payload`,
        details: result.error.issues.map(issue => ({
          path: issue.path.join('.'),
          message: issue.message,
        })),
      });
      return;
    }
    handler(result.data);
  };
}
