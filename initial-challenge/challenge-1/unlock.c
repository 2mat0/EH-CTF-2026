#include <stdio.h>

int main() {
    printf("Checking security clearance...\n\n");

    FILE *f = fopen("/var/prison/exit.txt", "r");
    if (!f) {
        printf("Access denied.\n");
        return 1;
    }

    printf("Access granted.\n-------------------\n");

    char c;
    while ((c = fgetc(f)) != EOF) {
        putchar(c);
    }

    fclose(f);
    return 0;
}
